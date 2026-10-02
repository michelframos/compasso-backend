<?php

namespace App\Modules\Academico\Http\Requests\MaterialTurma;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreMaterialTurmaRequest",
    title: "Store Material Turma Request",
    description: "Request para criar um novo material de turma",
    required: ["id_turma", "titulo", "file"],
    properties: [
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "titulo", type: "string", example: "Apostila de Violino"),
        new OA\Property(property: "descricao", type: "string", example: "Material de apoio para as primeiras aulas", nullable: true),
        new OA\Property(property: "file", type: "string", format: "binary", description: "O arquivo sendo enviado"),
        new OA\Property(property: "publico", type: "boolean", example: true, description: "Se o material é visível para os alunos", default: true)
    ]
)]
class StoreMaterialTurmaRequest extends FormRequest
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
            'id_turma' => ['required', 'integer', InstituicaoContext::existsRule('turmas')],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:10240'], // max 10MB
            'publico' => ['boolean']
        ];
    }
}
