<?php

namespace App\Modules\Core\Http\Requests\Assinatura;

use App\Modules\Core\Models\AssinaturaCobranca;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CheckoutAssinaturaRequest',
    required: ['id_plano_assinatura', 'method'],
    properties: [
        new OA\Property(property: 'id_plano_assinatura', type: 'integer', example: 2),
        new OA\Property(property: 'method', type: 'string', enum: ['pix', 'credit_card', 'boleto'], example: 'pix'),
    ]
)]
class CheckoutAssinaturaRequest extends FormRequest
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
            'method' => ['required', 'string', Rule::in(AssinaturaCobranca::METODOS)],
        ];
    }
}
