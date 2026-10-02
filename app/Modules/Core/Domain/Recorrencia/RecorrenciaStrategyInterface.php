<?php

namespace App\Modules\Core\Domain\Recorrencia;

use Carbon\CarbonInterface;

interface RecorrenciaStrategyInterface
{
    public function frequencia(): string;

    /**
     * Data da ocorrência de índice $i (0 = a própria data base). Não altera $base.
     */
    public function avancar(CarbonInterface $base, int $i): CarbonInterface;
}
