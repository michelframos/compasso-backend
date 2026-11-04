<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\Matricula;
use Illuminate\Database\Eloquent\Builder;

class ListMatriculasDoAlunoQuery
{
    public const RELACOES = [
        'turma.curso',
        'turma.nivel',
        'turma.professor.usuario',
        'turma.horarios',
        'curso',
        'nivel',
        'professor.usuario',
    ];

    public function build(int $idAluno, bool $encerradas): Builder
    {
        $query = Matricula::query()
            ->where('id_aluno', $idAluno)
            ->with(self::RELACOES);

        if ($encerradas) {
            $query->whereIn('status', Matricula::STATUS_ENCERRADOS);
        } else {
            $query->vigentes();
        }

        return $query->orderByDesc('data')->orderByDesc('id');
    }
}
