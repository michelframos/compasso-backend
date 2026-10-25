<?php

namespace App\Modules\Academico\Http\Requests\SolicitacaoAula;

use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DecidirSolicitacaoAulaRequest',
    required: ['decisao'],
    description: 'Ao aprovar, os campos de ajuste (opcionais) substituem o que o professor sugeriu.',
    properties: [
        new OA\Property(property: 'decisao', type: 'string', enum: ['aprovada', 'rejeitada']),
        new OA\Property(property: 'motivo_decisao', type: 'string', nullable: true, description: 'Obrigatório ao rejeitar'),
        new OA\Property(property: 'data_sugerida', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'hora_inicio_sugerida', type: 'string', nullable: true, example: '14:00'),
        new OA\Property(property: 'hora_termino_sugerida', type: 'string', nullable: true, example: '15:00'),
        new OA\Property(property: 'id_professor_substituto', type: 'integer', nullable: true),
        new OA\Property(property: 'destino_cobranca', type: 'string', nullable: true, enum: ['proxima_aula', 'cancelar']),
        new OA\Property(property: 'avisar_alunos', type: 'boolean', description: 'Ao aprovar: avisa alunos e responsáveis (padrão: o que o professor pediu)'),
    ]
)]
class DecidirSolicitacaoAulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decisao' => ['required', Rule::in([SolicitacaoAula::STATUS_APROVADA, SolicitacaoAula::STATUS_REJEITADA])],
            'motivo_decisao' => ['required_if:decisao,'.SolicitacaoAula::STATUS_REJEITADA, 'nullable', 'string', 'max:1000'],
            'data_sugerida' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio_sugerida' => ['sometimes', 'nullable', 'date_format:H:i'],
            'hora_termino_sugerida' => ['sometimes', 'nullable', 'date_format:H:i'],
            'id_professor_substituto' => ['sometimes', 'nullable', 'integer', InstituicaoContext::existsRule('professores')],
            'destino_cobranca' => ['sometimes', 'nullable', Rule::in(SolicitacaoAula::DESTINOS_COBRANCA)],
            'avisar_alunos' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_decisao.required_if' => 'Informe o motivo da rejeição.',
            'data_sugerida.after_or_equal' => 'A nova aula não pode ser em data passada.',
        ];
    }
}
