<?php

namespace App\Modules\Pessoas\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Pessoas\Models\MedidaAluno;
use App\Modules\Pessoas\Support\EdicaoMedidasDoAluno;
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

    public function update(User $user, MedidaAluno $medida): Response
    {
        if (! in_array($user->role, ['aluno', 'responsavel'], true)) {
            return Response::allow();
        }

        $vinculo = $user->role === 'aluno'
            ? $this->ehOProprioAluno($user, $medida->id_aluno)
            : $this->ehResponsavelPeloAluno($user, $medida->id_aluno);

        if (! $vinculo) {
            return Response::deny('Acesso restrito: esta medida não é sua.');
        }

        return $this->permitirSe(
            EdicaoMedidasDoAluno::permitida(),
            'A escola não permite que o aluno altere as medidas.'
        );
    }
}
