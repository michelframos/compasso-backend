<?php

namespace App\Modules\Espetaculos\UseCases\Ensaio;

use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\Espetaculo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class RegrasDoEnsaio
{
    /** O ensaio precisa ser até o dia do espetáculo, e o espetáculo não pode estar concluído ou cancelado. */
    public static function validarData(Apresentacao $apresentacao, string $data): void
    {
        $espetaculo = $apresentacao->espetaculo;

        if ($espetaculo === null) {
            return;
        }

        if (in_array($espetaculo->status, Espetaculo::STATUS_ENCERRADOS, true)) {
            throw ValidationException::withMessages([
                'id_apresentacao' => 'Não é possível agendar ensaios de um espetáculo concluído ou cancelado.',
            ]);
        }

        $dataEvento = Carbon::parse($espetaculo->data_evento)->toDateString();

        if ($data > $dataEvento) {
            throw ValidationException::withMessages([
                'data' => 'O ensaio deve ser até a data do espetáculo ('.Carbon::parse($dataEvento)->format('d/m/Y').').',
            ]);
        }
    }
}
