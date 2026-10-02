<?php

namespace App\Modules\Core\Domain\Recorrencia;

use Carbon\CarbonInterface;

class Mensal implements RecorrenciaStrategyInterface
{
    public function frequencia(): string
    {
        return 'mensal';
    }

    /**
     * Sem overflow: 31/01 + 1 mês = 28/02 (e não 03/03).
     */
    public function avancar(CarbonInterface $base, int $i): CarbonInterface
    {
        return $base->copy()->addMonthsNoOverflow($i);
    }
}
