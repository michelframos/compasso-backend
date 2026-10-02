<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use Illuminate\Auth\Access\Response;

class MaterialTurmaPolicy
{
    use VerificaVinculos;

    public function delete(User $user, MaterialTurma $material): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            $this->lecionaPara($user, $material->turma?->id_professor),
            'Acesso Restrito: Você não leciona nesta turma e não pode excluir este material.'
        );
    }
}
