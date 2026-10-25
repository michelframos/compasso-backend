<?php

namespace App\Modules\Academico\UseCases\AvaliacaoAluno;

use App\Modules\Academico\Models\AvaliacaoAluno;
use App\Modules\Pessoas\Models\Professor;

class RegistrarAvaliacaoUseCase
{
    /**
     * @param  array{id_aluno: int, id_turma?: ?int, data: string, tipo: string, nota?: ?float, conceito?: ?string, comentario?: ?string}  $dados
     */
    public function execute(array $dados, Professor $autor): AvaliacaoAluno
    {
        return AvaliacaoAluno::create([
            ...$dados,
            'id_professor' => $autor->id,
        ]);
    }
}
