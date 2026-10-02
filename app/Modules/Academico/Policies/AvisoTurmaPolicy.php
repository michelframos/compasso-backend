<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use Illuminate\Auth\Access\Response;

class AvisoTurmaPolicy
{
    use VerificaVinculos;

    private const EQUIPE = ['admin', 'secretaria'];

    public function viewAny(User $user): Response
    {
        return $this->permitirSe(
            $user->role === 'professor' || in_array($user->role, self::EQUIPE, true),
            'Somente professores e a secretaria acompanham os avisos.'
        );
    }

    public function view(User $user, AvisoTurma $aviso): Response
    {
        if (in_array($user->role, self::EQUIPE, true)) {
            return Response::allow();
        }

        return $this->permitirSe(
            $user->role === 'professor' && (
                $aviso->id_usuario_autor === $user->id
                || $this->lecionaPara($user, $aviso->id_professor)
                || $this->lecionaPara($user, $aviso->turma?->id_professor)
            ),
            'Acesso restrito: este aviso não é de uma turma sua.'
        );
    }

    /** Sem turma, o aviso vai para alunos escolhidos; o use case confere se cada um é do professor. */
    public function create(User $user, ?Turma $turma = null): Response
    {
        if (in_array($user->role, self::EQUIPE, true)) {
            return Response::allow();
        }

        if ($user->role !== 'professor') {
            return Response::deny('Somente professores e a secretaria enviam avisos.');
        }

        return $this->permitirSe(
            ! $turma || $this->lecionaPara($user, $turma->id_professor),
            'Acesso restrito: Você não leciona nesta turma.'
        );
    }
}
