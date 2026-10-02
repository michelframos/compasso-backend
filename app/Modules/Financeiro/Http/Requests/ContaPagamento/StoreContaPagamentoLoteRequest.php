<?php

namespace App\Modules\Financeiro\Http\Requests\ContaPagamento;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreContaPagamentoLoteRequest',
    type: 'object',
    required: ['conta_ids', 'data_pagamento', 'forma_pagamento'],
    properties: [
        new OA\Property(
            property: 'conta_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            example: [1, 2, 3]
        ),
        new OA\Property(property: 'data_pagamento', type: 'string', format: 'date', example: '2026-03-15'),
        new OA\Property(property: 'forma_pagamento', type: 'string', example: 'PIX'),
        new OA\Property(property: 'observacoes', type: 'string', nullable: true),
    ]
)]
class StoreContaPagamentoLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conta_ids' => 'required|array|min:1',
            'conta_ids.*' => ['required', 'integer', InstituicaoContext::existsRule('contas')],
            'data_pagamento' => 'required|date',
            'forma_pagamento' => 'required|string|max:100',
            'observacoes' => 'nullable|string',
        ];
    }
}
