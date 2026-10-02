<?php

namespace App\Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

class Telefone
{
    protected string $numero;

    public function __construct(string $numero)
    {
        $this->numero = $this->sanitize($numero);
        $this->validate($this->numero);
    }

    protected function sanitize(string $numero): string
    {
        return preg_replace('/\D/', '', $numero);
    }

    protected function validate(string $numero): void
    {
        // Aceita fixo (10) e celular (11)
        if (strlen($numero) < 10 || strlen($numero) > 11) {
            throw new InvalidArgumentException('Telefone inválido. Deve ter 10 ou 11 dígitos.');
        }
    }

    public function __toString(): string
    {
        return $this->format();
    }

    public function format(): string
    {
        $len = strlen($this->numero);
        if ($len === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $this->numero);
        }

        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $this->numero);
    }

    public function getValue(): string
    {
        return $this->numero;
    }
}
