<?php

namespace App\Modules\Core\Http\Concerns;

use Illuminate\Contracts\Database\Query\Builder;

trait ResolvesSorting
{
    /**
     * Aplica ordenação a partir de `sort_by`/`sort_order` da request, aceitando
     * apenas as chaves informadas em $allowed (lista de colunas ou mapa chave => coluna).
     *
     * @param  array<int, string>|array<string, string>  $allowed
     */
    protected function applySorting(Builder $query, array $allowed, string $default, string $defaultOrder = 'asc'): Builder
    {
        $map = array_is_list($allowed) ? array_combine($allowed, $allowed) : $allowed;
        $sortBy = (string) request()->query('sort_by', $default);
        $column = $map[$sortBy] ?? $map[$default] ?? $default;

        return $query->orderBy($column, $this->sortOrder($defaultOrder));
    }

    protected function sortOrder(string $default = 'asc'): string
    {
        $order = strtolower((string) request()->query('sort_order', $default));

        return in_array($order, ['asc', 'desc'], true) ? $order : $default;
    }
}
