<?php

namespace App\Modules\Financeiro\Queries\Situacao;

use Illuminate\Database\Eloquent\Builder;

interface SituacaoContaFiltro
{
    public function aplicar(Builder $query, string $hoje): void;
}
