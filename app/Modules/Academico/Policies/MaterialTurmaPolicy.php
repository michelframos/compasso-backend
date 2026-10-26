<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use Illuminate\Auth\Access\Response;

class MaterialTurmaPolicy
{
    use VerificaVinculos;

    public function viewAnyDoAluno(User $user, int $alunoId): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $alunoId),
                'Acesso restrito: você só pode ver os seus materiais.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $alunoId),
                'Acesso restrito: você não é o responsável por este aluno.'
            ),
            default => Response::allow(),
        };
    }

    public function delete(User $user, MaterialTurma $material): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            $this->lecionaPara($user, $material->turma?->id_professor),
            'Acesso Restrito: Você não leciona nesta turma e não pode excluir este material.'
        );
    }
}
