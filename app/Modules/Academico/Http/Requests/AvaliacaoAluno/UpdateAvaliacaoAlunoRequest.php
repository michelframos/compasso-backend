<?php

namespace App\Modules\Academico\Http\Requests\AvaliacaoAluno;

use App\Modules\Academico\Models\AvaliacaoAluno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateAvaliacaoAlunoRequest',
    title: 'Update Avaliação Aluno Request',
    description: 'Edição de uma avaliação (aluno e turma não mudam). A avaliação precisa continuar com nota ou conceito.',
    properties: [
        new OA\Property(property: 'data', type: 'string', format: 'date', example: '2026-09-30'),
        new OA\Property(property: 'tipo', type: 'string', enum: AvaliacaoAluno::TIPOS, example: 'teorica'),
        new OA\Property(property: 'nota', type: 'number', nullable: true, minimum: 0, maximum: 10, example: 9),
        new OA\Property(property: 'conceito', type: 'string', nullable: true, enum: AvaliacaoAluno::CONCEITOS, example: 'excelente'),
        new OA\Property(property: 'comentario', type: 'string', nullable: true, maxLength: 2000),
    ]
)]
class UpdateAvaliacaoAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...StoreAvaliacaoAlunoRequest::regrasDaAvaliacao('sometimes'),
            'nota' => ['sometimes', 'nullable', 'numeric', 'between:0,10'],
            'conceito' => ['sometimes', 'nullable', Rule::in(AvaliacaoAluno::CONCEITOS)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var AvaliacaoAluno $avaliacao */
                $avaliacao = $this->route('avaliacaoAluno');
                $nota = $this->has('nota') ? $this->input('nota') : $avaliacao->nota;
                $conceito = $this->has('conceito') ? $this->input('conceito') : $avaliacao->conceito;

                if (($nota === null || $nota === '') && ($conceito === null || $conceito === '')) {
                    $validator->errors()->add('nota', 'Informe a nota ou o conceito.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return StoreAvaliacaoAlunoRequest::mensagens();
    }
}
