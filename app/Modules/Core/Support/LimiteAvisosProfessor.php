<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\Instituicao;

/** Quantos avisos manuais um professor pode enviar por dia, definido pela escola. */
final class LimiteAvisosProfessor
{
    public const PADRAO = 10;

    public const MINIMO = 1;

    public const MAXIMO = 100;

    public static function da(?Instituicao $instituicao): int
    {
        $limite = (int) $instituicao?->limite_avisos_professor_dia;

        return $limite >= self::MINIMO ? $limite : self::PADRAO;
    }
}
