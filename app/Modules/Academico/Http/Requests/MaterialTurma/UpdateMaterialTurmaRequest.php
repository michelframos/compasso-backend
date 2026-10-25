<?php

namespace App\Modules\Academico\Http\Requests\MaterialTurma;

use App\Modules\Academico\Models\MaterialTurma;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateMaterialTurmaRequest",
    title: "Update Material Turma Request",
    description: "Request para atualizar um material de turma existente. Enviar um arquivo substitui o link (e vice-versa).",
    properties: [
        new OA\Property(property: "titulo", type: "string", example: "Apostila de Violino (Atualizada)"),
        new OA\Property(property: "descricao", type: "string", example: "Material revisado", nullable: true),
        new OA\Property(property: "file", type: "string", format: "binary", description: "O novo arquivo (opcional). Máx. 10MB"),
        new OA\Property(property: "link", type: "string", format: "uri", example: "https://youtube.com/watch?v=abc", nullable: true),
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
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:' . implode(',', MaterialTurma::EXTENSOES_PERMITIDAS)],
            'link' => ['nullable', 'url:http,https', 'max:2048'],
            'publico' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Tipo de arquivo não permitido.',
            'file.max' => 'O arquivo deve ter no máximo 10MB.',
            'link.url' => 'Informe um link válido (http ou https).',
        ];
    }
}
