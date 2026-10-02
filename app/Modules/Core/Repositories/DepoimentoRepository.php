<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Contracts\DepoimentoRepositoryInterface;
use App\Modules\Core\Models\Depoimento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DepoimentoRepository implements DepoimentoRepositoryInterface
{
    public function paginate(
        ?string $search = null,
        ?bool $aprovado = null,
        bool $withTrashed = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = $withTrashed ? Depoimento::withTrashed() : Depoimento::query();

        if (filled($search)) {
            $query->where(function ($q) use ($search): void {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('escola', 'like', "%{$search}%")
                    ->orWhere('cargo', 'like', "%{$search}%");
            });
        }

        if ($aprovado !== null) {
            $query->where('aprovado', $aprovado);
        }

        return $query->orderBy('ordem')->orderByDesc('id')->paginate($perPage);
    }

    public function findById(int|string $id, bool $withTrashed = false): Depoimento
    {
        $query = $withTrashed ? Depoimento::withTrashed() : Depoimento::query();

        return $query->findOrFail($id);
    }

    public function create(array $data): Depoimento
    {
        return Depoimento::create($data);
    }

    public function update(Depoimento $depoimento, array $data): Depoimento
    {
        $depoimento->update($data);

        return $depoimento->fresh();
    }

    public function delete(Depoimento $depoimento): void
    {
        $depoimento->delete();
    }

    public function aprovadosParaVitrine(): Collection
    {
        return Depoimento::query()->aprovados()->get();
    }
}
