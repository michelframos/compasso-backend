<?php

namespace App\Modules\Financeiro\Queries\Situacao;

use Illuminate\Database\Eloquent\Builder;

class EmAberto implements SituacaoContaFiltro
{
    public function aplicar(Builder $query, string $hoje): void
    {
        $query->whereNotIn('status', ['pago', 'cancelado']);
    }
}
