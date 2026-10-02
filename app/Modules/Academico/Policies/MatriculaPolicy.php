<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use Illuminate\Auth\Access\Response;

class MatriculaPolicy
{
    use VerificaVinculos;

    public function view(User $user, Matricula $matricula): Response
    {
        return match ($user->role) {
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $matricula->id_aluno),
                'Acesso restrito: Aluno não vinculado a você.'
            ),
            default => $this->viewMensalidades($user, $matricula),
        };
    }

    /** Responsáveis não são checados nesta rota. */
    public function viewMensalidades(User $user, Matricula $matricula): Response
    {
        return match ($user->role) {
            'professor' => $this->permitirSe(
                $this->lecionaPara($user, $matricula->idProfessorResponsavel()),
                'Acesso restrito: Este aluno não pertence a uma turma ou curso que você leciona.'
            ),
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $matricula->id_aluno),
                'Acesso restrito: Esta matrícula não é sua.'
            ),
            default => Response::allow(),
        };
    }

    public function viewAnyDoAluno(User $user, int $alunoId): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $alunoId),
                'Acesso Restrito: Você só pode ver as suas próprias matrículas.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $alunoId),
                'Acesso Restrito: Você não é o responsável por este aluno.'
            ),
            default => Response::allow(),
        };
    }

    public function viewAnyDaTurma(User $user, int $turmaId): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            $this->lecionaPara($user, Turma::findOrFail($turmaId)->id_professor),
            'Acesso restrito: Você não leciona nesta turma.'
        );
    }
}
