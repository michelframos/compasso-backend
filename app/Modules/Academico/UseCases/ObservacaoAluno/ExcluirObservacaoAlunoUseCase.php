<?php

namespace App\Modules\Academico\UseCases\ObservacaoAluno;

use App\Modules\Academico\Models\ObservacaoAluno;

class ExcluirObservacaoAlunoUseCase
{
    public function execute(ObservacaoAluno $observacao): void
    {
        $observacao->delete();
    }
}
