<?php

namespace App\Modules\Core\Domain\Recorrencia;

use Carbon\CarbonInterface;

class Semanal implements RecorrenciaStrategyInterface
{
    public function frequencia(): string
    {
        return 'semanal';
    }

    public function avancar(CarbonInterface $base, int $i): CarbonInterface
    {
        return $base->copy()->addWeeks($i);
    }
}
