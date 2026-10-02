<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Core\Models\Depoimento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface DepoimentoRepositoryInterface
{
    public function paginate(
        ?string $search = null,
        ?bool $aprovado = null,
        bool $withTrashed = false,
        int $perPage = 15,
    ): LengthAwarePaginator;

    public function findById(int|string $id, bool $withTrashed = false): Depoimento;

    public function create(array $data): Depoimento;

    public function update(Depoimento $depoimento, array $data): Depoimento;

    public function delete(Depoimento $depoimento): void;

    /**
     * @return Collection<int, Depoimento>
     */
    public function aprovadosParaVitrine(): Collection;
}
