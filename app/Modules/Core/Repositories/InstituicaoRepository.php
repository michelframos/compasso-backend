<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Contracts\InstituicaoRepositoryInterface;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InstituicaoRepository implements InstituicaoRepositoryInterface
{
    public function paginate(
        ?string $search = null,
        ?string $status = null,
        bool $withTrashed = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = $withTrashed ? Instituicao::withTrashed() : Instituicao::query();

        if (filled($search)) {
            $query->where(function ($q) use ($search): void {
                $q->where('nome_fantasia', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('cnpj', 'like', "%{$search}%");
            });
        }

        if (filled($status)) {
            $query->where('status', $status);
        }

        return $query->with('planoAssinatura')->orderBy('nome_fantasia')->paginate($perPage);
    }

    public function findById(int|string $id, bool $withTrashed = false): Instituicao
    {
        $query = $withTrashed ? Instituicao::withTrashed() : Instituicao::query();

        return $query->with('planoAssinatura')->findOrFail($id);
    }

    public function create(array $data): Instituicao
    {
        return Instituicao::create($data);
    }

    public function update(Instituicao $instituicao, array $data): Instituicao
    {
        $instituicao->update($data);

        return $instituicao->fresh(['planoAssinatura']);
    }

    public function delete(Instituicao $instituicao): void
    {
        $instituicao->delete();
    }
}
