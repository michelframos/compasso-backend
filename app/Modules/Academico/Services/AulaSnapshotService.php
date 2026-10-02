<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\AulaTurma;

/**
 * Congela na aula os valores financeiros vigentes (hora-aula, comissão e mensalidade)
 * no momento em que ela é concluída. Valores da turma sobrepõem os do professor;
 * aulas individuais (sem turma) usam só os do professor.
 */
class AulaSnapshotService
{
    public function aplicarSeConcluida(array $data, ?AulaTurma $aula = null): array
    {
        if (($data['status'] ?? null) !== 'concluida') {
            return $data;
        }

        $turmaId = array_key_exists('id_turma', $data) ? $data['id_turma'] : $aula?->id_turma;
        $turma = $turmaId ? \App\Models\Turma::find($turmaId) : null;
        $professorId = ($data['id_professor'] ?? null) ?? $aula?->id_professor ?? $turma?->id_professor;
        $professor = $professorId ? \App\Models\Professor::find($professorId) : null;

        if (! $professor) {
            return $data;
        }

        $data['valor_hora_aula_aplicado'] = $turma?->valor_hora_aula_especifico ?? $professor->valor_hora_aula;
        $data['percentual_comissao_aplicado'] = $turma?->percentual_comissao_especifico ?? $professor->comissao;
        $data['valor_mensalidade_aplicado'] = $turma?->valor_mensalidade;

        return $data;
    }
}
