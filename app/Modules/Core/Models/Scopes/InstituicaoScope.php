<?php

namespace App\Modules\Core\Models\Scopes;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra queries pelo id_instituicao do contexto ativo.
 * Sem contexto definido, não aplica filtro (compatível com CLI e migrações).
 */
class InstituicaoScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = InstituicaoContext::id();

        if ($id !== null) {
            $builder->where($model->getTable().'.id_instituicao', $id);
        }
    }
}
