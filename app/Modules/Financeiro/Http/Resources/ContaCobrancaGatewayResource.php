<?php

namespace App\Modules\Financeiro\Http\Resources;

use App\Modules\Financeiro\Models\ContaCobrancaGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ContaCobrancaGatewayResource',
    title: 'Cobrança online da conta',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'id_conta', type: 'integer'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'awaiting_payment', 'paid', 'overdue', 'canceled', 'failed']),
        new OA\Property(property: 'method', type: 'string', enum: ['pix', 'credit_card', 'boleto']),
        new OA\Property(property: 'amount', type: 'number', format: 'float'),
        new OA\Property(property: 'aberta', type: 'boolean', description: 'Ainda aguardando pagamento'),
        new OA\Property(property: 'paga', type: 'boolean'),
        new OA\Property(property: 'invoice_url', type: 'string', nullable: true),
        new OA\Property(property: 'pix_copy_paste', type: 'string', nullable: true),
        new OA\Property(property: 'pix_qr_code', type: 'string', nullable: true),
        new OA\Property(property: 'last_error', type: 'string', nullable: true),
        new OA\Property(property: 'id_conta_pagamento', type: 'integer', nullable: true),
    ]
)]
class ContaCobrancaGatewayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ContaCobrancaGateway $cobranca */
        $cobranca = $this->resource;

        return [
            'id' => $cobranca->id,
            'id_conta' => $cobranca->id_conta,
            'status' => $cobranca->status,
            'method' => $cobranca->method,
            'amount' => (float) $cobranca->amount,
            'aberta' => $cobranca->isAberta(),
            'paga' => $cobranca->status === ContaCobrancaGateway::STATUS_PAID,
            'invoice_url' => $cobranca->invoice_url,
            'pix_copy_paste' => $cobranca->pix_copy_paste,
            'pix_qr_code' => $cobranca->pix_qr_code,
            'last_error' => $cobranca->last_error,
            'id_conta_pagamento' => $cobranca->id_conta_pagamento,
            'created_at' => $cobranca->created_at?->toIso8601String(),
            'updated_at' => $cobranca->updated_at?->toIso8601String(),
        ];
    }
}
