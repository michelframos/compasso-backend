<?php

namespace App\Modules\Relatorios\Services\Remuneracao;

use Carbon\CarbonImmutable;

/** Mês de referência da remuneração (`Y-m`). */
final class Competencia
{
    public readonly CarbonImmutable $inicio;

    public readonly CarbonImmutable $fim;

    private function __construct(public readonly int $ano, public readonly int $mes)
    {
        $this->inicio = CarbonImmutable::create($ano, $mes, 1)->startOfDay();
        $this->fim = $this->inicio->endOfMonth()->startOfDay();
    }

    public static function de(string $mes): self
    {
        [$ano, $numero] = array_map('intval', explode('-', $mes));

        return new self($ano, $numero);
    }

    public static function atual(): self
    {
        return new self((int) now()->year, (int) now()->month);
    }

    public static function deAnoMes(int $ano, int $mes): self
    {
        return new self($ano, $mes);
    }

    public function rotulo(): string
    {
        return sprintf('%04d-%02d', $this->ano, $this->mes);
    }

    public function emAndamento(): bool
    {
        return $this->fim->greaterThanOrEqualTo(today());
    }
}
