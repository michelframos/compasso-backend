<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Core\Support\PermissoesProfessorAulas;
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

    /** Criação direta pelo professor: só para si e se a escola deixar livre. */
    public function create(User $user, ?int $idProfessor = null): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        if (! $this->lecionaPara($user, $idProfessor)) {
            return Response::deny('Acesso restrito: o professor só agenda aulas para si mesmo.');
        }

        return $this->modoLivre(PermissoesProfessorAulas::CRIAR, 'criar');
    }

    public function update(User $user, AulaTurma $aula): Response
    {
        return $this->alteracaoDireta($user, $aula, PermissoesProfessorAulas::EDITAR, 'editar');
    }

    public function delete(User $user, AulaTurma $aula): Response
    {
        return $this->alteracaoDireta($user, $aula, PermissoesProfessorAulas::EXCLUIR, 'cancelar ou excluir');
    }

    /** Somente administradores alteram aulas já concluídas (liberados pelo Gate::before). */
    public function alterarConcluida(User $user, AulaTurma $aula): Response
    {
        return Response::deny('Aulas dadas e finalizadas só podem ser alteradas por administradores.');
    }

    private function alteracaoDireta(User $user, AulaTurma $aula, string $acao, string $verbo): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        if (! $this->lecionaNaAula($user, $aula)) {
            return Response::deny('Acesso restrito: Você não leciona nesta aula.');
        }

        return $this->modoLivre($acao, $verbo);
    }

    private function modoLivre(string $acao, string $verbo): Response
    {
        return match (PermissoesProfessorAulas::modo($acao)) {
            PermissoesProfessorAulas::LIVRE => Response::allow(),
            PermissoesProfessorAulas::APROVACAO => Response::deny("A escola exige aprovação da secretaria para {$verbo} aulas. Envie uma solicitação pela área do professor."),
            default => Response::deny("A escola não permite que professores {$verbo} aulas."),
        };
    }

    /** O substituto (professor da aula) e o titular da turma fazem a chamada. */
    private function somenteProfessorDaAula(User $user, AulaTurma $aula, string $mensagem): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe($this->lecionaNaAula($user, $aula), $mensagem);
    }

    private function lecionaNaAula(User $user, AulaTurma $aula): bool
    {
        return $this->lecionaPara($user, $aula->id_professor) || $this->lecionaPara($user, $aula->turma?->id_professor);
    }
}
