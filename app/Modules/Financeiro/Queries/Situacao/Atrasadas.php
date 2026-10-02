<?php

namespace App\Modules\Financeiro\Queries\Situacao;

use Illuminate\Database\Eloquent\Builder;

class Atrasadas implements SituacaoContaFiltro
{
    public function aplicar(Builder $query, string $hoje): void
    {
        $query->where(function (Builder $q) use ($hoje) {
            $q->where('status', 'vencido')
                ->orWhere(function (Builder $q2) use ($hoje) {
                    $q2->whereIn('status', ['pendente', 'pago_parcialmente'])
                        ->whereDate('data_vencimento', '<', $hoje);
                });
        });
    }
}
