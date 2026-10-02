<?php

namespace App\Modules\Financeiro\Http\Requests\ContaPagamento;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreContaPagamentoRequest",
    type: "object",
    required: ["valor_pago", "data_pagamento", "forma_pagamento"],
    properties: [
        new OA\Property(property: "valor_pago", type: "number", format: "float", example: 50.00),
        new OA\Property(property: "data_pagamento", type: "string", format: "date", example: "2026-03-15"),
        new OA\Property(property: "forma_pagamento", type: "string", example: "PIX"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Recibo 1234")
    ]
)]
class StoreContaPagamentoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'valor_pago' => ['required', 'numeric', 'min:0.01'],
            'data_pagamento' => ['required', 'date'],
            'forma_pagamento' => ['required', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string']
        ];
    }
}
