<?php

namespace App\Modules\Academico\UseCases\AvaliacaoAluno;

use App\Modules\Academico\Models\AvaliacaoAluno;

class ExcluirAvaliacaoUseCase
{
    public function execute(AvaliacaoAluno $avaliacao): void
    {
        $avaliacao->delete();
    }
}
