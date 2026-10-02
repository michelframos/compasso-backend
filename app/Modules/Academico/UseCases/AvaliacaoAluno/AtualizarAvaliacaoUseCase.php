<?php

namespace App\Modules\Academico\UseCases\AvaliacaoAluno;

use App\Modules\Academico\Models\AvaliacaoAluno;

class AtualizarAvaliacaoUseCase
{
    /**
     * @param  array{data?: string, tipo?: string, nota?: ?float, conceito?: ?string, comentario?: ?string}  $dados
     */
    public function execute(AvaliacaoAluno $avaliacao, array $dados): AvaliacaoAluno
    {
        $avaliacao->update($dados);

        return $avaliacao;
    }
}
