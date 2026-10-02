<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Policies\Concerns\VerificaAlunosDoProfessor;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Auth\Access\Response;

/** Registrada como a ability `verFichaAluno`, pois o model Aluno pertence ao módulo Pessoas. */
class FichaAlunoPolicy
{
    use VerificaAlunosDoProfessor;
    use VerificaVinculos;

    public function view(User $user, Aluno $aluno): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            $this->ensinaAoAluno($user, $aluno->id),
            'Acesso restrito: Este aluno não está matriculado em nenhuma de suas turmas.'
        );
    }
}
