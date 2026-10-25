<?php

namespace App\Modules\Relatorios\Services\Remuneracao;

use App\Modules\Academico\Models\Turma;

final class TurmaResumida
{
    /** @return array{id: int, descricao: ?string, curso: ?array{nome: string}, nivel: ?array{nome: string}}|null */
    public static function de(?Turma $turma): ?array
    {
        if (! $turma) {
            return null;
        }

        return [
            'id' => $turma->id,
            'descricao' => $turma->descricao,
            'curso' => $turma->curso ? ['nome' => $turma->curso->nome] : null,
            'nivel' => $turma->nivel ? ['nome' => $turma->nivel->nome] : null,
        ];
    }
}
