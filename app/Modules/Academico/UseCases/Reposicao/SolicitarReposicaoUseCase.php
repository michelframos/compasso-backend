<?php

namespace App\Modules\Academico\UseCases\Reposicao;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\SolicitacaoReposicao;
use App\Modules\Academico\Support\RegrasReposicaoAluno;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitarReposicaoUseCase
{
    public function __construct(private readonly RegrasReposicaoAluno $regras) {}

    /**
     * @param  array{id_aula_turma_perdida: int, id_aula_turma_destino?: int|null, motivo: string}  $dados
     */
    public function execute(int $idAluno, array $dados): SolicitacaoReposicao
    {
        $perdida = $this->regras->aulaPerdida($idAluno, (int) $dados['id_aula_turma_perdida']);
        $this->regras->validarPrazo($perdida);
        $this->regras->validarLimite($idAluno);
        $this->validarDuplicidade($idAluno, $perdida->id);

        $destino = $this->destino($idAluno, $perdida, $dados['id_aula_turma_destino'] ?? null);

        return DB::transaction(fn () => SolicitacaoReposicao::create([
            'id_aluno' => $idAluno,
            'id_aula_turma_perdida' => $perdida->id,
            'id_aula_turma_destino' => $destino?->id,
            'motivo' => $dados['motivo'],
            'status' => SolicitacaoReposicao::STATUS_PENDENTE,
        ]))->fresh(['destino.turma']);
    }

    private function validarDuplicidade(int $idAluno, int $idAula): void
    {
        $aberta = SolicitacaoReposicao::query()
            ->where('id_aluno', $idAluno)
            ->where('id_aula_turma_perdida', $idAula)
            ->whereIn('status', SolicitacaoReposicao::ABERTAS)
            ->exists();

        if ($aberta) {
            throw ValidationException::withMessages([
                'id_aula_turma_perdida' => 'Já existe um pedido pendente ou aprovado para esta falta.',
            ]);
        }
    }

    private function destino(int $idAluno, AulaTurma $perdida, mixed $idDestino): ?AulaTurma
    {
        if ($idDestino === null || $idDestino === '') {
            return null;
        }

        $destino = AulaTurma::query()->with('turma')->whereKey((int) $idDestino)->first();

        if ($destino === null) {
            throw ValidationException::withMessages(['id_aula_turma_destino' => 'Aula de destino não encontrada.']);
        }

        $this->regras->validarDestino($idAluno, $perdida, $destino);

        return $destino;
    }
}
