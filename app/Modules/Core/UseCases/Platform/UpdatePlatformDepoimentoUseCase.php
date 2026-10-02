<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\DepoimentoRepositoryInterface;
use App\Modules\Core\Models\Depoimento;

class UpdatePlatformDepoimentoUseCase
{
    public function __construct(
        private readonly DepoimentoRepositoryInterface $depoimentos,
    ) {}

    public function execute(Depoimento $depoimento, array $data): Depoimento
    {
        unset($data['aprovado'], $data['aprovado_em']);

        return $this->depoimentos->update($depoimento, $data);
    }
}
