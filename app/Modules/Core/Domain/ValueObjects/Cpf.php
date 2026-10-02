<?php

namespace App\Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

class Cpf
{
    private string $cpf;

    public function __construct(string $cpf)
    {
        $this->cpf = $this->sanitize($cpf);
        $this->validate($this->cpf);
    }

    private function sanitize(string $cpf): string
    {
        return preg_replace('/\D/', '', $cpf);
    }

    private function validate(string $cpf): void
    {
        if (strlen($cpf) != 11) {
            throw new InvalidArgumentException('CPF deve ter 11 dígitos.');
        }

        if (preg_match('/(\d)\1{10}/', $cpf)) {
            throw new InvalidArgumentException('CPF inválido.');
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                throw new InvalidArgumentException('CPF inválido.');
            }
        }
    }

    public function __toString(): string
    {
        return $this->format();
    }

    public function format(): string
    {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $this->cpf);
    }

    public function getValue(): string
    {
        return $this->cpf;
    }
}
