<?php

namespace App\Modules\Espetaculos\Http\Requests\Ensaio;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateEnsaioRequest',
    title: 'Update Ensaio Request',
    description: 'Edição de ensaio (a apresentação não muda). Hora de início e término devem ser enviadas juntas.',
    required: ['data', 'hora_inicio', 'hora_termino'],
    properties: [
        new OA\Property(property: 'id_professor', type: 'integer', nullable: true, description: 'Só a secretaria altera o condutor', example: 2),
        new OA\Property(property: 'data', type: 'string', format: 'date', example: '2026-11-12'),
        new OA\Property(property: 'hora_inicio', type: 'string', example: '14:00'),
        new OA\Property(property: 'hora_termino', type: 'string', example: '15:30'),
        new OA\Property(property: 'local', type: 'string', nullable: true, maxLength: 255),
        new OA\Property(property: 'observacoes', type: 'string', nullable: true, maxLength: 2000),
    ]
)]
class UpdateEnsaioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return StoreEnsaioRequest::regrasDoEnsaio();
    }

    public function messages(): array
    {
        return StoreEnsaioRequest::mensagens();
    }
}
