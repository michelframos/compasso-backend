<?php

namespace App\Modules\Core\Support;

final class NumeroWhatsapp
{
    /** Só dígitos; números nacionais (DDD + número) ganham o DDI 55. */
    public static function formatar(?string $numero): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $numero) ?? '';

        if (strlen($digitos) === 10 || strlen($digitos) === 11) {
            return '55'.$digitos;
        }

        return $digitos !== '' ? $digitos : null;
    }
}
