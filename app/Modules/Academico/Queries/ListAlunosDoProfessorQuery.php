<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Core\Models\User;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListAlunosDoProfessorQuery
{
    public const TIPOS = ['turma', 'curso'];

    /**
     * Alunos com matrícula vigente em turma do professor ou por curso atribuída a ele.
     *
     * @param  array<string, mixed>  $filtros  tipo (turma|curso), search (nome ou e-mail)
     */
    public function build(User $user, array $filtros = []): Builder
    {
        $tipo = $filtros['tipo'] ?? null;

        $matriculasDoProfessor = fn (Builder|HasMany $q) => $q
            ->visivelPara($user)
            ->vigentes()
            ->when($tipo, fn ($m) => $m->where('tipo', $tipo));

        $query = Aluno::query()
            ->whereHas('matriculas', $matriculasDoProfessor)
            ->with([
                'usuario',
                'matriculas' => fn ($q) => $matriculasDoProfessor($q)
                    ->with(['turma.curso', 'turma.nivel', 'curso', 'nivel'])
                    ->orderByDesc('data'),
            ]);

        if (! empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->whereHas('usuario', fn (Builder $u) => $u
                ->where('nome', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return $query->orderBy(
            User::query()->select('nome')->whereColumn('usuarios.id', 'alunos.id_usuario')->limit(1)
        )->orderBy('alunos.id');
    }
}
