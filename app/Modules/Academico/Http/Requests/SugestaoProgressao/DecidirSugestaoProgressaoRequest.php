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
        new OA\Property(property: 'reajustar_mensalidades', type: 'boolean', nullable: true, example: true),
        new OA\Property(property: 'gerar_novas_mensalidades', type: 'boolean', nullable: true, example: false),
        new OA\Property(property: 'valor_mensalidade_geracao', type: 'number', format: 'float', nullable: true, example: 180.00),
        new OA\Property(property: 'quantidade_parcelas', type: 'integer', nullable: true, example: 6),
        new OA\Property(property: 'dia_vencimento', type: 'integer', nullable: true, example: 10),
        new OA\Property(property: 'data_inicio_mensalidades', type: 'string', format: 'date', nullable: true),
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
            'reajustar_mensalidades' => ['sometimes', 'boolean'],
            'gerar_novas_mensalidades' => ['sometimes', 'boolean'],
            'valor_mensalidade_geracao' => ['nullable', 'numeric', 'min:0'],
            'quantidade_parcelas' => ['nullable', 'integer', 'min:1', 'max:24'],
            'dia_vencimento' => ['nullable', 'integer', 'min:1', 'max:31'],
            'data_inicio_mensalidades' => ['nullable', 'date'],
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
