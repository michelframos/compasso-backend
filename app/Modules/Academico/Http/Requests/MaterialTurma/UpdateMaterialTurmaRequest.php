<?php

namespace App\Modules\Academico\Http\Requests\MaterialTurma;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateMaterialTurmaRequest",
    title: "Update Material Turma Request",
    description: "Request para atualizar um material de turma existente",
    properties: [
        new OA\Property(property: "titulo", type: "string", example: "Apostila de Violino (Atualizada)"),
        new OA\Property(property: "descricao", type: "string", example: "Material revisado", nullable: true),
        new OA\Property(property: "file", type: "string", format: "binary", description: "O novo arquivo (opcional)"),
        new OA\Property(property: "publico", type: "boolean", example: true, description: "Se o material é visível para os alunos")
    ]
)]
class UpdateMaterialTurmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('publico')) {
            $this->merge([
                'publico' => filter_var($this->publico, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'], // max 10MB
            'publico' => ['boolean']
        ];
    }
}
