<?php

namespace App\Modules\Academico\Http\Requests\Reposicao;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SolicitarReposicaoRequest',
    required: ['id_aula_turma_perdida', 'motivo'],
    properties: [
        new OA\Property(property: 'id_aula_turma_perdida', type: 'integer'),
        new OA\Property(property: 'id_aula_turma_destino', type: 'integer', nullable: true),
        new OA\Property(property: 'motivo', type: 'string', maxLength: 2000),
        new OA\Property(property: 'id_aluno', type: 'integer', nullable: true, description: 'Obrigatório para o responsável'),
    ]
)]
class SolicitarReposicaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_aula_turma_perdida' => ['required', 'integer'],
            'id_aula_turma_destino' => ['nullable', 'integer'],
            'motivo' => ['required', 'string', 'max:2000'],
            'id_aluno' => ['sometimes', 'integer'],
        ];
    }
}
