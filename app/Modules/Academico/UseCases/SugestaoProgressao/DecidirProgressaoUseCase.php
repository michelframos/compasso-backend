<?php

namespace App\Modules\Academico\UseCases\SugestaoProgressao;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Services\ProgressaoNivelService;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecidirProgressaoUseCase
{
    public function __construct(private readonly ProgressaoNivelService $progressao)
    {
    }

    /**
     * Ao aprovar: matrícula em turma é transferida para a turma de destino (com registro no histórico);
     * matrícula por curso tem o nível trocado.
     *
     * @param  array{decisao: string, id_turma_destino?: ?int, motivo_decisao?: ?string}  $dados
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
                $idTurmaDestino = $this->aplicarProgressao($sugestao, $matricula, $dados['id_turma_destino'] ?? null);
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

    private function aplicarProgressao(SugestaoProgressao $sugestao, Matricula $matricula, ?int $idTurmaDestino): ?int
    {
        if (! $matricula->estaVigente()) {
            throw ValidationException::withMessages(['id_matricula' => 'A matrícula não está mais vigente.']);
        }

        if ($matricula->tipo === 'curso') {
            $matricula->update(['id_nivel' => $sugestao->id_nivel_sugerido]);

            return null;
        }

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

        return $turma->id;
    }
}
