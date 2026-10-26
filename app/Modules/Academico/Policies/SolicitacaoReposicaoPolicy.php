<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\SolicitacaoReposicao;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use Illuminate\Auth\Access\Response;

class SolicitacaoReposicaoPolicy
{
    use VerificaVinculos;

    public function viewAnyDoAluno(User $user, int $alunoId): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $alunoId),
                'Acesso restrito: você só pede as suas reposições.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $alunoId),
                'Acesso restrito: você não é o responsável por este aluno.'
            ),
            default => Response::deny('Acesso restrito: apenas o aluno ou o responsável pede reposição.'),
        };
    }

    public function decidir(User $user, SolicitacaoReposicao $solicitacao): Response
    {
        if ($user->role === 'secretaria') {
            return Response::allow();
        }

        if ($user->role !== 'professor') {
            return Response::deny('Acesso restrito: a secretaria ou o professor da turma decide a reposição.');
        }

        $perdida = $solicitacao->perdida;
        $destino = $solicitacao->destino;

        return $this->permitirSe(
            $this->lecionaPara($user, $perdida?->id_professor)
                || $this->lecionaPara($user, $perdida?->turma?->id_professor)
                || $this->lecionaPara($user, $destino?->id_professor)
                || $this->lecionaPara($user, $destino?->turma?->id_professor),
            'Acesso restrito: você não leciona nesta turma.'
        );
    }
}
