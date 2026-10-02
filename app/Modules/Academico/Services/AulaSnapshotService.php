<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\AulaTurma;

/**
 * Congela na aula os valores financeiros vigentes (hora-aula, comissão e mensalidade)
 * no momento em que ela é concluída.
 */
class AulaSnapshotService
{
    public function aplicarSeConcluida(array $data, ?AulaTurma $aula = null): array
    {
        if (($data['status'] ?? null) !== 'concluida') {
            return $data;
        }

        $turmaId = $data['id_turma'] ?? $aula?->id_turma;
        $professorId = $data['id_professor'] ?? $aula?->id_professor;

        if (! $turmaId || ! $professorId) {
            return $data;
        }

        $turma = \App\Models\Turma::find($turmaId);
        $professor = \App\Models\Professor::find($professorId);

        if ($turma && $professor) {
            $data['valor_hora_aula_aplicado'] = $turma->valor_hora_aula_especifico ?? $professor->valor_hora_aula;
            $data['percentual_comissao_aplicado'] = $turma->percentual_comissao_especifico ?? $professor->comissao;
            $data['valor_mensalidade_aplicado'] = $turma->valor_mensalidade;
        }

        return $data;
    }
}
