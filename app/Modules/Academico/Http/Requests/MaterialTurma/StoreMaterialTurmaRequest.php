<?php

namespace App\Modules\Academico\Http\Requests\MaterialTurma;

use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreMaterialTurmaRequest",
    title: "Store Material Turma Request",
    description: "Request para criar um novo material de turma. Informe um arquivo ou um link.",
    required: ["id_turma", "titulo"],
    properties: [
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "titulo", type: "string", example: "Apostila de Violino"),
        new OA\Property(property: "descricao", type: "string", example: "Material de apoio para as primeiras aulas", nullable: true),
        new OA\Property(property: "file", type: "string", format: "binary", description: "Arquivo (obrigatório quando não houver link). Máx. 10MB"),
        new OA\Property(property: "link", type: "string", format: "uri", example: "https://youtube.com/watch?v=abc", description: "Link externo (obrigatório quando não houver arquivo)", nullable: true),
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
            'descricao' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'required_without:link', 'file', 'max:10240', 'mimes:' . implode(',', MaterialTurma::EXTENSOES_PERMITIDAS)],
            'link' => ['nullable', 'required_without:file', 'url:http,https', 'max:2048'],
            'publico' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required_without' => 'Envie um arquivo ou informe um link.',
            'link.required_without' => 'Informe um link ou envie um arquivo.',
            'file.mimes' => 'Tipo de arquivo não permitido.',
            'file.max' => 'O arquivo deve ter no máximo 10MB.',
            'link.url' => 'Informe um link válido (http ou https).',
        ];
    }
}
