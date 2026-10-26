<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListMateriaisDoAlunoQuery
{
    /** Materiais públicos das turmas em que o aluno tem matrícula. */
    public function build(User $user, int $idAluno, ?int $idTurma = null): Builder
    {
        return MaterialTurma::query()
            ->with(['turma.curso', 'turma.nivel'])
            ->visivelPara($user)
            ->whereHas('turma.matriculas', fn (Builder $matricula) => $matricula->where('id_aluno', $idAluno))
            ->when($idTurma !== null, fn (Builder $query) => $query->where('id_turma', $idTurma))
            ->orderByDesc('id');
    }
}
