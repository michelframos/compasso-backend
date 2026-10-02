<?php

namespace Tests\Unit;

use App\Modules\Core\Domain\Recorrencia\RecorrenciaResolver;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecorrenciaResolverTest extends TestCase
{
    public static function casos(): array
    {
        return [
            'diaria' => ['diaria', '2026-01-30', 2, '2026-02-01'],
            'semanal' => ['semanal', '2026-01-01', 3, '2026-01-22'],
            'quinzenal' => ['quinzenal', '2026-01-01', 2, '2026-01-29'],
            'mensal sem overflow' => ['mensal', '2026-01-31', 1, '2026-02-28'],
            'mensal volta ao dia 31' => ['mensal', '2026-01-31', 2, '2026-03-31'],
        ];
    }

    #[DataProvider('casos')]
    public function test_avanca_conforme_frequencia(string $frequencia, string $base, int $i, string $esperado): void
    {
        $data = (new RecorrenciaResolver())->resolve($frequencia)->avancar(Carbon::parse($base), $i);

        $this->assertSame($esperado, $data->format('Y-m-d'));
    }

    public function test_nao_altera_a_data_base(): void
    {
        $base = Carbon::parse('2026-01-01');

        (new RecorrenciaResolver())->resolve('semanal')->avancar($base, 4);

        $this->assertSame('2026-01-01', $base->format('Y-m-d'));
    }

    public function test_frequencia_desconhecida_lanca_excecao(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RecorrenciaResolver())->resolve('anual');
    }
}
