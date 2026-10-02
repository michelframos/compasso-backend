<?php

namespace App\Modules\Core\Domain\Recorrencia;

use Carbon\CarbonInterface;

class Quinzenal implements RecorrenciaStrategyInterface
{
    public function frequencia(): string
    {
        return 'quinzenal';
    }

    public function avancar(CarbonInterface $base, int $i): CarbonInterface
    {
        return $base->copy()->addWeeks($i * 2);
    }
}
