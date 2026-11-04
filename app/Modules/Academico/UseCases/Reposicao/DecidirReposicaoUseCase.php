<?php

namespace App\Modules\Academico\UseCases\Reposicao;

use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\SolicitacaoReposicao;
use App\Modules\Academico\Support\RegrasReposicaoAluno;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecidirReposicaoUseCase
{
    public function __construct(private readonly RegrasReposicaoAluno $regras) {}

    /** @param  array{decisao: string, id_aula_turma_destino?: int|null}  $dados */
    public function execute(SolicitacaoReposicao $solicitacao, array $dados, User $decisor): SolicitacaoReposicao
    {
        return DB::transaction(function () use ($solicitacao, $dados, $decisor) {
            $solicitacao = SolicitacaoReposicao::query()->lockForUpdate()->findOrFail($solicitacao->id);

            if (! $solicitacao->estaPendente()) {
                throw ValidationException::withMessages(['status' => 'Esta reposição já foi decidida.']);
            }

            $destinoId = $solicitacao->id_aula_turma_destino;

            if ($dados['decisao'] === SolicitacaoReposicao::STATUS_APROVADA) {
                $informado = $dados['id_aula_turma_destino'] ?? null;
                $destinoId = $informado !== null && $informado !== '' ? (int) $informado : $destinoId;
                $destino = $this->destino($solicitacao, $destinoId);

                AulaPresenca::updateOrCreate(
                    ['id_aula_turma' => $destino->id, 'id_aluno' => $solicitacao->id_aluno],
                    ['status' => 'presente', 'observacao' => $solicitacao->motivo]
                );
            }

            $solicitacao->update([
                'id_aula_turma_destino' => $destinoId,
                'status' => $dados['decisao'],
                'id_usuario_decisor' => $decisor->id,
                'decidido_em' => now(),
            ]);

            return $solicitacao->fresh(['aluno.usuario', 'perdida.turma', 'destino.turma']);
        });
    }

    private function destino(SolicitacaoReposicao $solicitacao, mixed $idDestino): AulaTurma
    {
        if ($idDestino === null || $idDestino === '') {
            throw ValidationException::withMessages([
                'id_aula_turma_destino' => 'Escolha a aula em que o aluno vai repor.',
            ]);
        }

        $destino = AulaTurma::query()->with('turma')->whereKey((int) $idDestino)->first();

        if ($destino === null) {
            throw ValidationException::withMessages(['id_aula_turma_destino' => 'Aula de destino não encontrada.']);
        }

        $perdida = AulaTurma::query()->with('turma')->findOrFail($solicitacao->id_aula_turma_perdida);
        $this->regras->validarDestino($solicitacao->id_aluno, $perdida, $destino);

        return $destino;
    }
}
