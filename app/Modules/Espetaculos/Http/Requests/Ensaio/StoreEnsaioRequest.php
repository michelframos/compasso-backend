<?php

namespace App\Modules\Espetaculos\Http\Requests\Ensaio;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreEnsaioRequest',
    title: 'Store Ensaio Request',
    description: 'Agendamento de ensaio de uma apresentação. Para o professor, o condutor é sempre ele mesmo.',
    required: ['id_apresentacao', 'data', 'hora_inicio', 'hora_termino'],
    properties: [
        new OA\Property(property: 'id_apresentacao', type: 'integer', example: 3),
        new OA\Property(property: 'id_professor', type: 'integer', nullable: true, description: 'Condutor do ensaio (só a secretaria escolhe; ignorado para o professor)', example: 2),
        new OA\Property(property: 'data', type: 'string', format: 'date', description: 'De hoje até a data do espetáculo', example: '2026-11-10'),
        new OA\Property(property: 'hora_inicio', type: 'string', example: '14:00'),
        new OA\Property(property: 'hora_termino', type: 'string', example: '15:30'),
        new OA\Property(property: 'local', type: 'string', nullable: true, maxLength: 255, example: 'Sala 2'),
        new OA\Property(property: 'observacoes', type: 'string', nullable: true, maxLength: 2000),
    ]
)]
class StoreEnsaioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_apresentacao' => ['required', 'integer', InstituicaoContext::existsRule('apresentacoes')->whereNull('deleted_at')],
            ...self::regrasDoEnsaio(),
            'data' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function regrasDoEnsaio(string $presenca = 'required'): array
    {
        return [
            'id_professor' => ['nullable', 'integer', InstituicaoContext::existsRule('professores')],
            'data' => [$presenca, 'date_format:Y-m-d'],
            'hora_inicio' => [$presenca, 'date_format:H:i'],
            'hora_termino' => [$presenca, 'date_format:H:i', 'after:hora_inicio'],
            'local' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
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
            'data.after_or_equal' => 'O ensaio não pode ser agendado no passado.',
            'hora_inicio.date_format' => 'Informe a hora de início no formato HH:MM.',
            'hora_termino.date_format' => 'Informe a hora de término no formato HH:MM.',
            'hora_termino.after' => 'A hora de término deve ser depois da hora de início.',
            'local.max' => 'O local deve ter no máximo 255 caracteres.',
            'observacoes.max' => 'As observações devem ter no máximo 2000 caracteres.',
        ];
    }
}
