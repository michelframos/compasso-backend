<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Core\Models\PlanoAssinatura;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PlanoAssinaturaRepositoryInterface
{
    public function paginate(
        ?string $search = null,
        ?bool $ativo = null,
        bool $withTrashed = false,
        int $perPage = 15,
    ): LengthAwarePaginator;

    public function findById(int|string $id, bool $withTrashed = false): PlanoAssinatura;

    public function create(array $data): PlanoAssinatura;

    public function update(PlanoAssinatura $plano, array $data): PlanoAssinatura;

    public function delete(PlanoAssinatura $plano): void;

    public function countInstituicoesVinculadas(PlanoAssinatura $plano): int;
}
