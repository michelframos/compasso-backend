<?php

namespace App\Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

class Whatsapp extends Telefone
{
    protected function validate(string $numero): void
    {
        // Whatsapp geralmente é celular (11 dígitos) no Brasil
        if (strlen($numero) != 11) {
            throw new InvalidArgumentException('Whatsapp inválido. Deve ser um número de celular com 11 dígitos.');
        }
    }
}
