<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\InstituicaoRepositoryInterface;
use App\Modules\Core\Models\InstituicaoUsuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPlatformInstituicaoUsuariosUseCase
{
    public function __construct(
        private readonly InstituicaoRepositoryInterface $instituicoes,
    ) {}

    public function execute(int $instituicaoId, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $instituicao = $this->instituicoes->findById($instituicaoId);

        $query = $instituicao->usuarios()
            ->wherePivot('status', InstituicaoUsuario::STATUS_ATIVO);

        if (filled($search)) {
            $query->where(function ($q) use ($search): void {
                $q->where('usuarios.nome', 'like', "%{$search}%")
                    ->orWhere('usuarios.email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('usuarios.nome')->paginate($perPage);
    }
}
