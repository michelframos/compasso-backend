<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Contracts\PlanoAssinaturaRepositoryInterface;
use App\Modules\Core\Models\PlanoAssinatura;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlanoAssinaturaRepository implements PlanoAssinaturaRepositoryInterface
{
    public function paginate(
        ?string $search = null,
        ?bool $ativo = null,
        bool $withTrashed = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = $withTrashed ? PlanoAssinatura::withTrashed() : PlanoAssinatura::query();

        if (filled($search)) {
            $query->where(function ($q) use ($search): void {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($ativo !== null) {
            $query->where('ativo', $ativo);
        }

        return $query->orderBy('nome')->paginate($perPage);
    }

    public function findById(int|string $id, bool $withTrashed = false): PlanoAssinatura
    {
        $query = $withTrashed ? PlanoAssinatura::withTrashed() : PlanoAssinatura::query();

        return $query->findOrFail($id);
    }

    public function create(array $data): PlanoAssinatura
    {
        return PlanoAssinatura::create($data);
    }

    public function update(PlanoAssinatura $plano, array $data): PlanoAssinatura
    {
        $plano->update($data);

        return $plano->fresh();
    }

    public function delete(PlanoAssinatura $plano): void
    {
        $plano->delete();
    }

    public function countInstituicoesVinculadas(PlanoAssinatura $plano): int
    {
        return $plano->instituicoes()->count();
    }
}
