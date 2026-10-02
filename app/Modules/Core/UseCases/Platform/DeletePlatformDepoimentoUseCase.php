<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\DepoimentoRepositoryInterface;
use App\Modules\Core\Models\Depoimento;

class DeletePlatformDepoimentoUseCase
{
    public function __construct(
        private readonly DepoimentoRepositoryInterface $depoimentos,
    ) {}

    public function execute(Depoimento $depoimento): void
    {
        $this->depoimentos->delete($depoimento);
    }
}
