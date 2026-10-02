<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\AvaliacaoAluno;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Policies\Concerns\VerificaAlunosDoProfessor;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Auth\Access\Response;

class AvaliacaoAlunoPolicy
{
    use VerificaAlunosDoProfessor;
    use VerificaVinculos;

    public function create(User $user, Aluno $aluno, ?Turma $turma = null): Response
    {
        if ($user->role !== 'professor') {
            return Response::deny('Somente professores registram avaliações.');
        }

        if ($turma !== null) {
            return $this->permitirSe(
                $this->ensinaAoAluno($user, $aluno->id, $turma->id),
                'Acesso restrito: Este aluno não está matriculado nesta turma ou você não leciona nela.'
            );
        }

        return $this->permitirSe(
            $this->ensinaAoAluno($user, $aluno->id),
            'Acesso restrito: Este aluno não tem matrícula vigente com você.'
        );
    }

    public function update(User $user, AvaliacaoAluno $avaliacao): Response
    {
        return $this->somenteAutor($user, $avaliacao, 'Você só pode editar as avaliações que registrou.');
    }

    public function delete(User $user, AvaliacaoAluno $avaliacao): Response
    {
        return $this->somenteAutor($user, $avaliacao, 'Você só pode excluir as avaliações que registrou.');
    }

    private function somenteAutor(User $user, AvaliacaoAluno $avaliacao, string $mensagem): Response
    {
        return $this->permitirSe(
            $user->role === 'professor' && $this->lecionaPara($user, $avaliacao->id_professor),
            $mensagem
        );
    }
}
