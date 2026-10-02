<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Core\Models\Instituicao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InstituicaoRepositoryInterface
{
    public function paginate(
        ?string $search = null,
        ?string $status = null,
        bool $withTrashed = false,
        int $perPage = 15,
    ): LengthAwarePaginator;

    public function findById(int|string $id, bool $withTrashed = false): Instituicao;

    public function create(array $data): Instituicao;

    public function update(Instituicao $instituicao, array $data): Instituicao;

    public function delete(Instituicao $instituicao): void;
}
