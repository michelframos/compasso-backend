<?php

namespace App\Modules\Academico\UseCases\ObservacaoAluno;

use App\Modules\Academico\Models\ObservacaoAluno;
use App\Modules\Pessoas\Models\Professor;

class CriarObservacaoAlunoUseCase
{
    /**
     * @param  array{id_aluno: int, id_turma?: ?int, tipo: string, texto: string, visivel_responsavel?: bool}  $dados
     */
    public function execute(array $dados, Professor $autor): ObservacaoAluno
    {
        return ObservacaoAluno::create([
            ...$dados,
            'id_professor' => $autor->id,
            'visivel_responsavel' => $dados['visivel_responsavel'] ?? false,
        ]);
    }
}
