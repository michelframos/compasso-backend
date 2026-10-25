<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\ObservacaoAluno;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Policies\Concerns\VerificaAlunosDoProfessor;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Auth\Access\Response;

class ObservacaoAlunoPolicy
{
    use VerificaAlunosDoProfessor;
    use VerificaVinculos;

    public function create(User $user, Aluno $aluno, ?Turma $turma = null): Response
    {
        if ($user->role !== 'professor') {
            return Response::deny('Somente professores registram observações pedagógicas.');
        }

        if ($turma !== null) {
            return $this->permitirSe(
                $this->ensinaAoAluno($user, $aluno->id, $turma->id),
                'Acesso restrito: Este aluno não está matriculado nesta turma ou você não leciona nela.'
            );
        }

        return $this->permitirSe(
            $this->ensinaAoAluno($user, $aluno->id),
            'Acesso restrito: Este aluno não está matriculado em nenhuma de suas turmas.'
        );
    }

    public function update(User $user, ObservacaoAluno $observacao): Response
    {
        return $this->somenteAutor($user, $observacao, 'Você só pode editar as observações que registrou.');
    }

    public function delete(User $user, ObservacaoAluno $observacao): Response
    {
        return $this->somenteAutor($user, $observacao, 'Você só pode excluir as observações que registrou.');
    }

    private function somenteAutor(User $user, ObservacaoAluno $observacao, string $mensagem): Response
    {
        return $this->permitirSe(
            $user->role === 'professor' && $this->lecionaPara($user, $observacao->id_professor),
            $mensagem
        );
    }
}
