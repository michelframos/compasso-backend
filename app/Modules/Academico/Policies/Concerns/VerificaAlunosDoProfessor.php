<?php

namespace App\Modules\Academico\Policies\Concerns;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Core\Models\User;

trait VerificaAlunosDoProfessor
{
    /** Aluno com matrícula vigente em uma turma do professor (ou na turma informada, que também deve ser dele). */
    protected function ensinaAoAluno(User $user, ?int $alunoId, ?int $turmaId = null): bool
    {
        return $alunoId !== null && Matricula::query()
            ->visivelPara($user)
            ->vigentes()
            ->where('id_aluno', $alunoId)
            ->when($turmaId !== null, fn ($q) => $q->where('id_turma', $turmaId))
            ->exists();
    }
}
