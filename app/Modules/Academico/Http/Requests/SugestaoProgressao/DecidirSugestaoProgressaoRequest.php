<?php

namespace App\Modules\Academico\Http\Requests\SugestaoProgressao;

use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DecidirSugestaoProgressaoRequest',
    title: 'Decidir Sugestão Progressão Request',
    description: 'Aprovação ou rejeição de uma sugestão pendente. Na matrícula em turma, aprovar exige a turma de destino (do nível sugerido e do mesmo curso); na matrícula por curso, o nível da matrícula é trocado.',
    required: ['decisao'],
    properties: [
        new OA\Property(property: 'decisao', type: 'string', enum: [SugestaoProgressao::STATUS_APROVADA, SugestaoProgressao::STATUS_REJEITADA], example: 'aprovada'),
        new OA\Property(property: 'id_turma_destino', type: 'integer', nullable: true, description: 'Obrigatório ao aprovar matrícula em turma', example: 7),
        new OA\Property(property: 'motivo_decisao', type: 'string', nullable: true, maxLength: 2000, description: 'Obrigatório ao rejeitar'),
    ]
)]
class DecidirSugestaoProgressaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decisao' => ['required', Rule::in([SugestaoProgressao::STATUS_APROVADA, SugestaoProgressao::STATUS_REJEITADA])],
            'id_turma_destino' => ['nullable', 'integer', InstituicaoContext::existsRule('turmas')->whereNull('deleted_at')],
            'motivo_decisao' => ['nullable', 'string', 'max:2000', 'required_if:decisao,'.SugestaoProgressao::STATUS_REJEITADA],
        ];
    }

    public function messages(): array
    {
        return [
            'decisao.in' => 'Decisão inválida.',
            'motivo_decisao.required_if' => 'Informe o motivo da rejeição.',
            'motivo_decisao.max' => 'O motivo deve ter no máximo 2000 caracteres.',
        ];
    }
}
