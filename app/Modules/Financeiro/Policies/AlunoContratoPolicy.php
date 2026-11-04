<?php

namespace App\Modules\Financeiro\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Financeiro\Models\AlunoContrato;
use Illuminate\Auth\Access\Response;

class AlunoContratoPolicy
{
    use VerificaVinculos;

    public function viewAnyDoAluno(User $user, int $alunoId): Response
    {
        return $this->doAluno($user, $alunoId);
    }

    public function view(User $user, AlunoContrato $contrato): Response
    {
        return $this->doAluno($user, (int) $contrato->aluno_id);
    }

    public function aceitar(User $user, AlunoContrato $contrato): Response
    {
        return $this->doAluno($user, (int) $contrato->aluno_id);
    }

    private function doAluno(User $user, int $alunoId): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $alunoId),
                'Acesso restrito: este contrato não é seu.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $alunoId),
                'Acesso restrito: você não é o responsável por este aluno.'
            ),
            default => Response::deny('Acesso restrito: apenas o aluno ou o responsável consulta os contratos.'),
        };
    }
}
