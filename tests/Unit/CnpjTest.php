<?php

namespace Tests\Unit;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CnpjTest extends TestCase
{
    public function test_aceita_cnpj_numerico_com_e_sem_mascara(): void
    {
        $this->assertSame('11222333000181', (new Cnpj('11.222.333/0001-81'))->getValue());
        $this->assertSame('11.222.333/0001-81', (new Cnpj('11222333000181'))->format());
    }

    public function test_aceita_cnpj_alfanumerico(): void
    {
        $cnpj = new Cnpj('12.abc.345/01de-35');

        $this->assertSame('12ABC34501DE35', $cnpj->getValue());
        $this->assertSame('12.ABC.345/01DE-35', $cnpj->format());
    }

    public function test_rejeita_digito_verificador_errado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cnpj('11.222.333/0001-82');
    }

    public function test_rejeita_caracteres_repetidos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cnpj('11.111.111/1111-11');
    }

    public function test_rejeita_tamanho_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cnpj('1122233300018');
    }

    public function test_normalize_remove_mascara_e_retorna_null_para_vazio(): void
    {
        $this->assertSame('11222333000181', Cnpj::normalize('11.222.333/0001-81'));
        $this->assertNull(Cnpj::normalize(''));
        $this->assertNull(Cnpj::normalize(null));
    }
}
