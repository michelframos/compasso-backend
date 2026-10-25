<?php

namespace App\Modules\Academico\UseCases\ObservacaoAluno;

use App\Modules\Academico\Models\ObservacaoAluno;

class AtualizarObservacaoAlunoUseCase
{
    /**
     * @param  array{tipo?: string, texto?: string, visivel_responsavel?: bool}  $dados
     */
    public function execute(ObservacaoAluno $observacao, array $dados): ObservacaoAluno
    {
        $observacao->update($dados);

        return $observacao;
    }
}
