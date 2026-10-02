<?php

namespace App\Modules\Academico\Queries;

use Illuminate\Database\Eloquent\Builder;

class ListMatriculasQuery
{
    public const RELACOES = ['aluno.usuario', 'turma.curso', 'turma.nivel', 'curso', 'nivel', 'professor.usuario'];

    /**
     * @param  array<string, mixed>  $filtros  status, search, sort_by
     * @param  'asc'|'desc'  $ordem
     */
    public function build(Builder $query, array $filtros, string $ordem = 'desc'): Builder
    {
        $query->with(self::RELACOES);

        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (! empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->whereHas('aluno.usuario', fn (Builder $q) => $q->where('nome', 'like', "%{$search}%"));
        }

        $this->ordenar($query, (string) ($filtros['sort_by'] ?? 'id'), $ordem);

        return $query;
    }

    private function ordenar(Builder $query, string $sortBy, string $ordem): void
    {
        match ($sortBy) {
            'id', 'data', 'status' => $query->orderBy("matriculas.{$sortBy}", $ordem),
            'aluno.nome' => $query->join('alunos', 'matriculas.id_aluno', '=', 'alunos.id')
                ->join('usuarios', 'alunos.id_usuario', '=', 'usuarios.id')
                ->orderBy('usuarios.nome', $ordem)
                ->select('matriculas.*'),
            'turma.nome' => $query->join('turmas', 'matriculas.id_turma', '=', 'turmas.id')
                ->join('cursos', 'turmas.id_curso', '=', 'cursos.id')
                ->orderBy('cursos.nome', $ordem)
                ->select('matriculas.*'),
            default => $query->orderBy('matriculas.id', 'desc'),
        };
    }
}
