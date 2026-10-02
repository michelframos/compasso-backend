<?php

namespace App\Modules\Pessoas\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Pessoas\Models\MedidaAluno;
use Illuminate\Auth\Access\Response;

class MedidaAlunoPolicy
{
    use VerificaVinculos;

    public function view(User $user, MedidaAluno $medida): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $medida->id_aluno),
                'Acesso restrito: Esta medida não pertence a você.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $medida->id_aluno),
                'Acesso restrito: Este aluno não está vinculado a você.'
            ),
            default => Response::allow(),
        };
    }

    public function viewAnyDoAluno(User $user, int $alunoId): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $alunoId),
                'Acesso restrito: Você só pode visualizar suas próprias medidas.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $alunoId),
                'Acesso restrito: Você não é responsável por este aluno.'
            ),
            default => Response::allow(),
        };
    }
}
