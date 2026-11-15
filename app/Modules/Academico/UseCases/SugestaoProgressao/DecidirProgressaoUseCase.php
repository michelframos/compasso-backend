<?php

namespace App\Modules\Academico\UseCases\SugestaoProgressao;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Services\ProgressaoNivelService;
use App\Modules\Academico\UseCases\Matricula\AjustarMensalidadesNaMudancaTurmaUseCase;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecidirProgressaoUseCase
{
    public function __construct(
        private readonly ProgressaoNivelService $progressao,
        private readonly AjustarMensalidadesNaMudancaTurmaUseCase $ajustarMensalidades,
    ) {
    }

    /**
     * Ao aprovar: matrícula em turma é transferida para a turma de destino (com registro no histórico);
     * matrícula por curso tem o nível trocado.
     *
     * @param  array{
     *   decisao: string,
     *   id_turma_destino?: ?int,
     *   motivo_decisao?: ?string,
     *   reajustar_mensalidades?: bool,
     *   gerar_novas_mensalidades?: bool,
     *   valor_mensalidade_geracao?: float|int|string|null,
     *   quantidade_parcelas?: int|null,
     *   dia_vencimento?: int|null,
     *   data_inicio_mensalidades?: string|null
     * }  $dados
     */
    public function execute(SugestaoProgressao $sugestao, array $dados, User $decisor): SugestaoProgressao
    {
        return DB::transaction(function () use ($sugestao, $dados, $decisor): SugestaoProgressao {
            $sugestao = SugestaoProgressao::query()->with('nivelSugerido')->lockForUpdate()->findOrFail($sugestao->id);

            if (! $sugestao->estaPendente()) {
                throw ValidationException::withMessages(['status' => 'Esta sugestão já foi decidida.']);
            }

            $idTurmaDestino = null;

            if ($dados['decisao'] === SugestaoProgressao::STATUS_APROVADA) {
                $matricula = Matricula::query()->with('turma')->lockForUpdate()->findOrFail($sugestao->id_matricula);
                $idTurmaDestino = $this->aplicarProgressao($sugestao, $matricula, $dados);
            }

            $sugestao->update([
                'status' => $dados['decisao'],
                'id_turma_destino' => $idTurmaDestino,
                'id_usuario_decisor' => $decisor->id,
                'decidido_em' => now(),
                'motivo_decisao' => $dados['motivo_decisao'] ?? null,
            ]);

            return $sugestao;
        });
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function aplicarProgressao(SugestaoProgressao $sugestao, Matricula $matricula, array $dados): ?int
    {
        if (! $matricula->estaVigente()) {
            throw ValidationException::withMessages(['id_matricula' => 'A matrícula não está mais vigente.']);
        }

        if ($matricula->tipo === 'curso') {
            $matricula->update(['id_nivel' => $sugestao->id_nivel_sugerido]);

            return null;
        }

        $idTurmaDestino = $dados['id_turma_destino'] ?? null;

        if ($idTurmaDestino === null) {
            throw ValidationException::withMessages(['id_turma_destino' => 'Escolha a turma de destino do aluno.']);
        }

        $turma = Turma::query()->lockForUpdate()->findOrFail($idTurmaDestino);
        $impedimento = $this->progressao->impedimentoDaTurmaDestino($turma, $matricula, (int) $sugestao->id_nivel_sugerido);

        if ($impedimento !== null) {
            throw ValidationException::withMessages(['id_turma_destino' => $impedimento]);
        }

        $matricula->transferirParaTurma(
            $turma->id,
            "Progressão de nível para {$sugestao->nivelSugerido?->nome} (sugestão #{$sugestao->id})"
        );

        $this->ajustarMensalidades->execute($matricula->fresh(['aluno.usuario']), $turma, [
            'reajustar_mensalidades' => (bool) ($dados['reajustar_mensalidades'] ?? false),
            'gerar_novas_mensalidades' => (bool) ($dados['gerar_novas_mensalidades'] ?? false),
            'valor' => $dados['valor_mensalidade_geracao'] ?? null,
            'quantidade_parcelas' => $dados['quantidade_parcelas'] ?? null,
            'dia_vencimento' => $dados['dia_vencimento'] ?? null,
            'data_inicio' => $dados['data_inicio_mensalidades'] ?? null,
        ]);

        return $turma->id;
    }
}
