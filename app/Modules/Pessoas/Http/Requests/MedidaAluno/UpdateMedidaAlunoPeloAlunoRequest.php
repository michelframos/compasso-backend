<?php

namespace App\Modules\Pessoas\Http\Requests\MedidaAluno;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateMedidaAlunoPeloAlunoRequest',
    title: 'UpdateMedidaAlunoPeloAlunoRequest',
    description: 'Medidas que o aluno ou o responsável podem corrigir. Não altera o aluno nem o status.',
    properties: [
        new OA\Property(property: 'medida_torax', type: 'number', format: 'float', nullable: true, example: 82),
        new OA\Property(property: 'medida_cintura', type: 'number', format: 'float', nullable: true, example: 68),
        new OA\Property(property: 'medida_quadril', type: 'number', format: 'float', nullable: true, example: 90),
        new OA\Property(property: 'medida_altura', type: 'number', format: 'float', nullable: true, example: 160),
    ]
)]
class UpdateMedidaAlunoPeloAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $medida = ['sometimes', 'nullable', 'numeric', 'between:0,999.99'];

        return [
            'id_aluno' => ['sometimes', 'integer'],
            'medida_torax' => $medida,
            'medida_cintura' => $medida,
            'medida_quadril' => $medida,
            'medida_altura' => $medida,
        ];
    }
}
