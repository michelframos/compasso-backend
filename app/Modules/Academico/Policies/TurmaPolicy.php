<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use Illuminate\Auth\Access\Response;

class TurmaPolicy
{
    use VerificaVinculos;

    public function view(User $user, Turma $turma): Response
    {
        return match ($user->role) {
            'professor' => $this->permitirSe(
                $this->lecionaPara($user, $turma->id_professor),
                'Acesso restrito: Você só pode visualizar suas próprias turmas.'
            ),
            'aluno' => $this->permitirSe(
                $turma->matriculas()->where('id_aluno', $user->aluno?->id)->exists(),
                'Acesso restrito: Você não está matriculado nesta turma.'
            ),
            default => Response::allow(),
        };
    }

    public function gerenciarAulas(User $user, Turma $turma): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            $this->lecionaPara($user, $turma->id_professor),
            'Acesso restrito: Você só pode criar ou gerenciar aulas das turmas que você leciona.'
        );
    }

    public function gerenciarMateriais(User $user, Turma $turma): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            $this->lecionaPara($user, $turma->id_professor),
            'Acesso Restrito: Você não leciona nesta turma.'
        );
    }
}
