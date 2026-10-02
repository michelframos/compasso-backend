<?php

namespace App\Modules\Financeiro\Queries\Situacao;

use Illuminate\Database\Eloquent\Builder;

class Pagas implements SituacaoContaFiltro
{
    public function aplicar(Builder $query, string $hoje): void
    {
        $query->where('status', 'pago');
    }
}
