<?php

namespace App\Modules\Core\Http\Requests\Assinatura;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SolicitarAssinaturaRequest',
    required: ['id_plano_assinatura'],
    properties: [
        new OA\Property(property: 'id_plano_assinatura', type: 'integer', example: 2),
        new OA\Property(property: 'observacao', type: 'string', nullable: true),
    ]
)]
class SolicitarAssinaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_plano_assinatura' => ['required', 'integer', 'exists:planos_assinatura,id'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
