<?php

namespace App\Modules\Core\Domain\Recorrencia;

use InvalidArgumentException;

class RecorrenciaResolver
{
    /** @var array<string, RecorrenciaStrategyInterface> */
    private array $strategies = [];

    /**
     * @param  iterable<RecorrenciaStrategyInterface>|null  $strategies
     */
    public function __construct(?iterable $strategies = null)
    {
        foreach ($strategies ?? [new Diaria, new Semanal, new Quinzenal, new Mensal] as $strategy) {
            $this->strategies[$strategy->frequencia()] = $strategy;
        }
    }

    public function resolve(string $frequencia): RecorrenciaStrategyInterface
    {
        return $this->strategies[$frequencia]
            ?? throw new InvalidArgumentException("Frequência de recorrência não suportada: {$frequencia}");
    }
}
