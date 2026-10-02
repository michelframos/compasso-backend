<?php

namespace App\Modules\Academico\Http\Requests\ObservacaoAluno;

use App\Modules\Academico\Models\ObservacaoAluno;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreObservacaoAlunoRequest',
    title: 'Store Observação Aluno Request',
    description: 'Registro de uma observação pedagógica sobre um aluno das turmas do professor',
    required: ['id_aluno', 'tipo', 'texto'],
    properties: [
        new OA\Property(property: 'id_aluno', type: 'integer', example: 5),
        new OA\Property(property: 'id_turma', type: 'integer', nullable: true, description: 'Turma do professor em que o aluno está matriculado (opcional)', example: 2),
        new OA\Property(property: 'tipo', type: 'string', enum: ObservacaoAluno::TIPOS, example: 'evolucao'),
        new OA\Property(property: 'texto', type: 'string', maxLength: 5000, example: 'Evoluiu bem na leitura de partitura.'),
        new OA\Property(property: 'visivel_responsavel', type: 'boolean', default: false, example: false),
    ]
)]
class StoreObservacaoAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_aluno' => ['required', 'integer', InstituicaoContext::existsRule('alunos')->whereNull('deleted_at')],
            'id_turma' => ['nullable', 'integer', InstituicaoContext::existsRule('turmas')->whereNull('deleted_at')],
            'tipo' => ['required', 'string', Rule::in(ObservacaoAluno::TIPOS)],
            'texto' => ['required', 'string', 'max:5000'],
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
