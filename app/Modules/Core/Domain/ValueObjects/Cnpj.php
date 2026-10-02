<?php

namespace App\Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Aceita o CNPJ numérico e o alfanumérico (12 caracteres [A-Z0-9] + 2 dígitos verificadores).
 */
class Cnpj
{
    private string $cnpj;

    public function __construct(string $cnpj)
    {
        $this->cnpj = (string) self::normalize($cnpj);
        $this->validate($this->cnpj);
    }

    public static function normalize(?string $cnpj): ?string
    {
        if ($cnpj === null) {
            return null;
        }

        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper($cnpj));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  string  $base  Os 12 primeiros caracteres do CNPJ, já normalizados.
     */
    public static function calcularDigitosVerificadores(string $base): string
    {
        $primeiro = self::calcularDigito($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $segundo = self::calcularDigito($base.$primeiro, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $primeiro.$segundo;
    }

    /**
     * @param  list<int>  $pesos
     */
    private static function calcularDigito(string $valor, array $pesos): int
    {
        $soma = 0;

        foreach ($pesos as $i => $peso) {
            $soma += (ord($valor[$i]) - 48) * $peso;
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }

    private function validate(string $cnpj): void
    {
        if (! preg_match('/^[A-Z0-9]{12}\d{2}$/', $cnpj)) {
            throw new InvalidArgumentException('CNPJ deve ter 14 caracteres.');
        }

        if (preg_match('/^(.)\1{13}$/', $cnpj)) {
            throw new InvalidArgumentException('CNPJ inválido.');
        }

        if (self::calcularDigitosVerificadores(substr($cnpj, 0, 12)) !== substr($cnpj, 12, 2)) {
            throw new InvalidArgumentException('CNPJ inválido.');
        }
    }

    public function __toString(): string
    {
        return $this->format();
    }

    public function format(): string
    {
        return preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $this->cnpj);
    }

    public function getValue(): string
    {
        return $this->cnpj;
    }
}
