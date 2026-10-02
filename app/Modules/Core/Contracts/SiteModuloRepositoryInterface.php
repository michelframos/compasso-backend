<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Core\Models\SiteModulo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SiteModuloRepositoryInterface
{
    public function paginate(
        ?string $search = null,
        ?bool $aprovado = null,
        bool $withTrashed = false,
        int $perPage = 15,
    ): LengthAwarePaginator;

    public function findById(int|string $id, bool $withTrashed = false): SiteModulo;

    public function create(array $data): SiteModulo;

    public function update(SiteModulo $modulo, array $data): SiteModulo;

    public function delete(SiteModulo $modulo): void;

    /**
     * @return Collection<int, SiteModulo>
     */
    public function aprovadosParaVitrine(): Collection;
}
