<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListAvisosTurmasQuery
{
    /** @param  array{id_turma?: int, origem?: string, search?: string}  $filtros */
    public function build(User $user, array $filtros): Builder
    {
        return AvisoTurma::query()
            ->visivelPara($user)
            ->comTotais()
            ->with(AvisoTurma::DETALHES)
            ->when($filtros['id_turma'] ?? null, fn (Builder $q, $turma) => $q->where('id_turma', $turma))
            ->when($filtros['origem'] ?? null, fn (Builder $q, $origem) => $q->where('origem', $origem))
            ->when($filtros['search'] ?? null, fn (Builder $q, $termo) => $q->where(fn (Builder $b) => $b
                ->where('titulo', 'like', "%{$termo}%")
                ->orWhereHas('autor', fn (Builder $a) => $a->where('nome', 'like', "%{$termo}%"))))
            ->latest('id');
    }
}
