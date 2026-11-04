<?php

namespace App\Modules\Instrumentos\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Instrumentos\Models\Instrumento;
use Illuminate\Auth\Access\Response;

class InstrumentoPolicy
{
    use VerificaVinculos;

    public function viewAnyDoAluno(User $user, int $alunoId): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $alunoId),
                'Acesso restrito: você só pode ver os seus instrumentos.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $alunoId),
                'Acesso restrito: você não é o responsável por este aluno.'
            ),
            default => Response::deny('Acesso restrito: apenas o aluno ou o responsável consulta os instrumentos.'),
        };
    }

    public function view(User $user, Instrumento $instrumento): Response
    {
        if (! in_array($user->role, ['aluno', 'responsavel'], true)) {
            return Response::allow();
        }

        return $this->viewAnyDoAluno($user, (int) $instrumento->id_aluno);
    }
}
