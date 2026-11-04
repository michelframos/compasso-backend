<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\JustificativaFalta;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use Illuminate\Auth\Access\Response;

class JustificativaFaltaPolicy
{
    use VerificaVinculos;

    public function viewAnyDoAluno(User $user, int $alunoId): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $alunoId),
                'Acesso restrito: você só registra as suas faltas.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $alunoId),
                'Acesso restrito: você não é o responsável por este aluno.'
            ),
            default => Response::deny('Acesso restrito: apenas o aluno ou o responsável registra faltas.'),
        };
    }

    public function decidir(User $user, JustificativaFalta $justificativa): Response
    {
        if ($user->role === 'secretaria') {
            return Response::allow();
        }

        if ($user->role !== 'professor') {
            return Response::deny('Acesso restrito: a secretaria ou o professor da turma decide a justificativa.');
        }

        $aula = $justificativa->aula;

        return $this->permitirSe(
            $this->lecionaPara($user, $aula?->id_professor) || $this->lecionaPara($user, $aula?->turma?->id_professor),
            'Acesso restrito: você não leciona nesta turma.'
        );
    }
}
