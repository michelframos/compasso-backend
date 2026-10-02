<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Contracts\SiteModuloRepositoryInterface;
use App\Modules\Core\Models\SiteModulo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SiteModuloRepository implements SiteModuloRepositoryInterface
{
    public function paginate(
        ?string $search = null,
        ?bool $aprovado = null,
        bool $withTrashed = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = $withTrashed ? SiteModulo::withTrashed() : SiteModulo::query();

        if (filled($search)) {
            $query->where(function ($q) use ($search): void {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('descricao', 'like', "%{$search}%");
            });
        }

        if ($aprovado !== null) {
            $query->where('aprovado', $aprovado);
        }

        return $query->orderBy('ordem')->orderByDesc('id')->paginate($perPage);
    }

    public function findById(int|string $id, bool $withTrashed = false): SiteModulo
    {
        $query = $withTrashed ? SiteModulo::withTrashed() : SiteModulo::query();

        return $query->findOrFail($id);
    }

    public function create(array $data): SiteModulo
    {
        return SiteModulo::create($data);
    }

    public function update(SiteModulo $modulo, array $data): SiteModulo
    {
        $modulo->update($data);

        return $modulo->fresh();
    }

    public function delete(SiteModulo $modulo): void
    {
        $modulo->delete();
    }

    public function aprovadosParaVitrine(): Collection
    {
        return SiteModulo::query()->aprovados()->get();
    }
}
