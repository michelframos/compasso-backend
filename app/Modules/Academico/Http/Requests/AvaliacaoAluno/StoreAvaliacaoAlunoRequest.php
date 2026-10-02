<?php

namespace App\Modules\Academico\Http\Requests\AvaliacaoAluno;

use App\Modules\Academico\Models\AvaliacaoAluno;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreAvaliacaoAlunoRequest',
    title: 'Store Avaliação Aluno Request',
    description: 'Registro de uma avaliação de um aluno do professor. Informe nota, conceito ou ambos.',
    required: ['id_aluno', 'data', 'tipo'],
    properties: [
        new OA\Property(property: 'id_aluno', type: 'integer', example: 5),
        new OA\Property(property: 'id_turma', type: 'integer', nullable: true, description: 'Turma do professor em que o aluno está matriculado (vazio = aulas individuais)', example: 2),
        new OA\Property(property: 'data', type: 'string', format: 'date', description: 'Não pode ser futura', example: '2026-09-30'),
        new OA\Property(property: 'tipo', type: 'string', enum: AvaliacaoAluno::TIPOS, example: 'pratica'),
        new OA\Property(property: 'nota', type: 'number', nullable: true, minimum: 0, maximum: 10, example: 8.5),
        new OA\Property(property: 'conceito', type: 'string', nullable: true, enum: AvaliacaoAluno::CONCEITOS, example: 'bom'),
        new OA\Property(property: 'comentario', type: 'string', nullable: true, maxLength: 2000),
    ]
)]
class StoreAvaliacaoAlunoRequest extends FormRequest
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
            ...self::regrasDaAvaliacao(),
            'nota' => ['nullable', 'numeric', 'between:0,10', 'required_without:conceito'],
            'conceito' => ['nullable', Rule::in(AvaliacaoAluno::CONCEITOS), 'required_without:nota'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function regrasDaAvaliacao(string $presenca = 'required'): array
    {
        return [
            'data' => [$presenca, 'date', 'before_or_equal:today'],
            'tipo' => [$presenca, 'string', Rule::in(AvaliacaoAluno::TIPOS)],
            'comentario' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return self::mensagens();
    }

    /** @return array<string, string> */
    public static function mensagens(): array
    {
        return [
            'data.before_or_equal' => 'A data da avaliação não pode ser futura.',
            'tipo.in' => 'Tipo de avaliação inválido.',
            'nota.between' => 'A nota deve estar entre 0 e 10.',
            'nota.required_without' => 'Informe a nota ou o conceito.',
            'conceito.required_without' => 'Informe a nota ou o conceito.',
            'conceito.in' => 'Conceito inválido.',
            'comentario.max' => 'O comentário deve ter no máximo 2000 caracteres.',
        ];
    }
}
