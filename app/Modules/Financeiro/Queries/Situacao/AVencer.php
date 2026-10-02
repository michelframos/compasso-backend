<?php

namespace App\Modules\Financeiro\Queries\Situacao;

use Illuminate\Database\Eloquent\Builder;

class AVencer implements SituacaoContaFiltro
{
    public function aplicar(Builder $query, string $hoje): void
    {
        $query->whereIn('status', ['pendente', 'pago_parcialmente'])
            ->whereDate('data_vencimento', '>', $hoje);
    }
}
