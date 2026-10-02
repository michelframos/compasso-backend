<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use Illuminate\Auth\Access\Response;

class AulaTurmaPolicy
{
    use VerificaVinculos;

    /** Mesma regra do escopo `AulaTurma::visivelPara`: professor da aula ou da turma. */
    public function view(User $user, AulaTurma $aula): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            $this->lecionaPara($user, $aula->id_professor) || $this->lecionaPara($user, $aula->turma?->id_professor),
            'Acesso restrito: Você não leciona nesta aula.'
        );
    }

    public function viewPresencas(User $user, AulaTurma $aula): Response
    {
        return $this->somenteProfessorDaAula($user, $aula, 'Acesso restrito: Você não leciona nesta turma.');
    }

    public function registrarPresencas(User $user, AulaTurma $aula): Response
    {
        return $this->somenteProfessorDaAula(
            $user,
            $aula,
            'Acesso restrito: Você não leciona nesta turma e não pode fazer chamada nela.'
        );
    }

    /** Somente administradores alteram aulas já concluídas (liberados pelo Gate::before). */
    public function alterarConcluida(User $user, AulaTurma $aula): Response
    {
        return Response::deny('Aulas dadas e finalizadas só podem ser alteradas por administradores.');
    }

    private function somenteProfessorDaAula(User $user, AulaTurma $aula, string $mensagem): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            $this->lecionaPara($user, $aula->turma?->id_professor ?? $aula->id_professor),
            $mensagem
        );
    }
}
