<?php

namespace App\Modules\Academico\Http\Requests\ObservacaoAluno;

use App\Modules\Academico\Models\ObservacaoAluno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateObservacaoAlunoRequest',
    title: 'Update Observação Aluno Request',
    description: 'Edição de uma observação pedagógica. Aluno e turma não podem ser alterados.',
    properties: [
        new OA\Property(property: 'tipo', type: 'string', enum: ObservacaoAluno::TIPOS, example: 'alerta'),
        new OA\Property(property: 'texto', type: 'string', maxLength: 5000, example: 'Faltou às duas últimas aulas sem aviso.'),
        new OA\Property(property: 'visivel_responsavel', type: 'boolean', example: true),
    ]
)]
class UpdateObservacaoAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['sometimes', 'required', 'string', Rule::in(ObservacaoAluno::TIPOS)],
            'texto' => ['sometimes', 'required', 'string', 'max:5000'],
            'visivel_responsavel' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.in' => 'Tipo de observação inválido.',
            'texto.required' => 'Escreva a observação.',
            'texto.max' => 'A observação deve ter no máximo 5000 caracteres.',
        ];
    }
}
