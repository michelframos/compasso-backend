<?php

namespace App\Modules\Core\Domain\Recorrencia;

use Carbon\CarbonInterface;

class Diaria implements RecorrenciaStrategyInterface
{
    public function frequencia(): string
    {
        return 'diaria';
    }

    public function avancar(CarbonInterface $base, int $i): CarbonInterface
    {
        return $base->copy()->addDays($i);
    }
}
