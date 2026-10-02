<?php

namespace App\Modules\Academico\Http\Requests\SugestaoProgressao;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreSugestaoProgressaoRequest',
    title: 'Store Sugestão Progressão Request',
    description: 'Sugestão do professor para mudar o aluno de nível; a secretaria aprova ou rejeita',
    required: ['id_matricula', 'id_nivel_sugerido', 'justificativa'],
    properties: [
        new OA\Property(property: 'id_matricula', type: 'integer', description: 'Matrícula vigente em turma ou curso do professor', example: 10),
        new OA\Property(property: 'id_nivel_sugerido', type: 'integer', description: 'Diferente do nível atual e do mesmo curso (quando o nível é vinculado a um curso)', example: 4),
        new OA\Property(property: 'justificativa', type: 'string', maxLength: 2000, example: 'Domina as escalas maiores e lê partitura com fluência.'),
    ]
)]
class StoreSugestaoProgressaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_matricula' => ['required', 'integer', InstituicaoContext::existsRule('matriculas')->whereNull('deleted_at')],
            'id_nivel_sugerido' => ['required', 'integer', InstituicaoContext::existsRule('niveis')->whereNull('deleted_at')],
            'justificativa' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_nivel_sugerido.required' => 'Escolha o nível sugerido.',
            'justificativa.required' => 'Explique por que o aluno deve mudar de nível.',
            'justificativa.max' => 'A justificativa deve ter no máximo 2000 caracteres.',
        ];
    }
}
