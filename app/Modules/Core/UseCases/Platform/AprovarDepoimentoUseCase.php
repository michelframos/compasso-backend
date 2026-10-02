<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\DepoimentoRepositoryInterface;
use App\Modules\Core\Models\Depoimento;

class AprovarDepoimentoUseCase
{
    public function __construct(
        private readonly DepoimentoRepositoryInterface $depoimentos,
    ) {}

    public function execute(Depoimento $depoimento): Depoimento
    {
        return $this->depoimentos->update($depoimento, [
            'aprovado' => true,
            'aprovado_em' => now(),
        ]);
    }
}
