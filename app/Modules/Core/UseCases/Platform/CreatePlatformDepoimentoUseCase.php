<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\DepoimentoRepositoryInterface;
use App\Modules\Core\Models\Depoimento;

class CreatePlatformDepoimentoUseCase
{
    public function __construct(
        private readonly DepoimentoRepositoryInterface $depoimentos,
    ) {}

    public function execute(array $data): Depoimento
    {
        $data['aprovado'] = false;
        $data['aprovado_em'] = null;
        $data['ordem'] ??= 0;
        $data['avatar_url'] = $data['avatar_url'] ?? null;

        return $this->depoimentos->create($data);
    }
}
