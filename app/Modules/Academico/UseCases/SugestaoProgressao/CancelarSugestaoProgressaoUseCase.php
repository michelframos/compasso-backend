<?php

namespace App\Modules\Academico\UseCases\SugestaoProgressao;

use App\Modules\Academico\Models\SugestaoProgressao;
use Illuminate\Validation\ValidationException;

class CancelarSugestaoProgressaoUseCase
{
    public function execute(SugestaoProgressao $sugestao): void
    {
        if (! $sugestao->estaPendente()) {
            throw ValidationException::withMessages([
                'status' => 'Só é possível cancelar sugestões ainda pendentes.',
            ]);
        }

        $sugestao->delete();
    }
}
