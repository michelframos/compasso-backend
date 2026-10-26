<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use Illuminate\Auth\Access\Response;

class CertificadoPolicy
{
    use VerificaVinculos;

    public function viewAnyDoAluno(User $user, int $alunoId): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $alunoId),
                'Acesso restrito: você só vê os seus certificados.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $alunoId),
                'Acesso restrito: você não é o responsável por este aluno.'
            ),
            default => Response::deny('Acesso restrito: apenas o aluno ou o responsável vê certificados.'),
        };
    }

    public function view(User $user, Certificado $certificado): Response
    {
        $alunoId = $certificado->matricula?->id_aluno;

        return $this->viewAnyDoAluno($user, (int) $alunoId);
    }

    public function emitir(User $user, Matricula $matricula): Response
    {
        return match ($user->role) {
            'secretaria' => Response::allow(),
            default => Response::deny('Acesso restrito: a secretaria emite o certificado.'),
        };
    }
}
