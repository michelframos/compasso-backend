<?php

namespace Tests;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Gera um CNPJ válido e determinístico a partir de uma semente.
     */
    protected function cnpjValido(string $seed): string
    {
        $base = str_pad((string) (abs(crc32($seed)) % 100000000), 8, '0', STR_PAD_LEFT).'0001';

        return $base.Cnpj::calcularDigitosVerificadores($base);
    }
}
