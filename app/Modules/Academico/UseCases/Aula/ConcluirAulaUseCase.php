<?php

namespace App\Modules\Academico\UseCases\Aula;

use App\Enums\TurmaStatus;
use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Services\AulaSnapshotService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConcluirAulaUseCase
{
    public function __construct(private readonly AulaSnapshotService $snapshots) {}

    /**
     * Registra a chamada e o conteúdo e marca a aula como concluída, congelando os valores financeiros.
     *
     * @param  array{conteudo_dado?: ?string, presencas: list<array{id_aluno: int, status: string, observacao?: ?string}>}  $dados
     */
    public function execute(AulaTurma $aula, array $dados): AulaTurma
    {
        $this->validar($aula, $dados['presencas']);

        return DB::transaction(function () use ($aula, $dados) {
            foreach ($dados['presencas'] as $presenca) {
                AulaPresenca::updateOrCreate(
                    ['id_aula_turma' => $aula->id, 'id_aluno' => $presenca['id_aluno']],
                    ['status' => $presenca['status'], 'observacao' => $presenca['observacao'] ?? null]
                );
            }

            $aula->update($this->snapshots->aplicarSeConcluida([
                'status' => 'concluida',
                'conteudo_dado' => array_key_exists('conteudo_dado', $dados) ? $dados['conteudo_dado'] : $aula->conteudo_dado,
            ], $aula));

            return $aula->refresh();
        });
    }

    private function validar(AulaTurma $aula, array $presencas): void
    {
        $turma = $aula->turma;

        abort_if(
            $turma && in_array($turma->status, [TurmaStatus::CONCLUIDA, TurmaStatus::CANCELADA], true),
            Response::HTTP_FORBIDDEN,
            'Não é possível alterar presenças de turmas concluídas ou canceladas.'
        );

        if ($aula->status === 'cancelada') {
            throw ValidationException::withMessages(['aula' => 'Não é possível concluir uma aula cancelada.']);
        }

        if ($aula->data->isAfter(today())) {
            throw ValidationException::withMessages(['aula' => 'Não é possível concluir uma aula que ainda não aconteceu.']);
        }

        $permitidos = $this->alunosDaAula($aula);
        $estranhos = array_diff(array_column($presencas, 'id_aluno'), $permitidos);

        if ($estranhos !== []) {
            throw ValidationException::withMessages(['presencas' => 'Há alunos na chamada que não pertencem a esta aula.']);
        }
    }

    /** @return list<int> */
    private function alunosDaAula(AulaTurma $aula): array
    {
        $alunos = $aula->turma?->matriculas()->pluck('id_aluno')->all() ?? [];

        if ($aula->id_aluno_especifico) {
            $alunos[] = $aula->id_aluno_especifico;
        }

        return array_map('intval', $alunos);
    }
}
