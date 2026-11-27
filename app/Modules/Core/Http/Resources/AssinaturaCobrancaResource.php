<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AssinaturaCobrancaResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'awaiting_payment', 'active', 'past_due', 'canceled', 'failed']),
        new OA\Property(property: 'method', type: 'string', enum: ['pix', 'credit_card', 'boleto']),
        new OA\Property(property: 'cycle', type: 'string', example: 'MONTHLY'),
        new OA\Property(property: 'amount', type: 'number', format: 'float'),
        new OA\Property(property: 'invoice_url', type: 'string', nullable: true),
        new OA\Property(property: 'next_due_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'last_error', type: 'string', nullable: true),
    ]
)]
class AssinaturaCobrancaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_plano_assinatura' => $this->id_plano_assinatura,
            'status' => $this->status,
            'method' => $this->method,
            'cycle' => $this->cycle,
            'amount' => (float) $this->amount,
            'invoice_url' => $this->invoice_url,
            'next_due_date' => $this->next_due_date?->toDateString(),
            'customer_email' => $this->customer_email,
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'plano' => $this->whenLoaded('plano', fn () => [
                'id' => $this->plano->id,
                'slug' => $this->plano->slug,
                'nome' => $this->plano->nome,
                'preco' => (float) $this->plano->preco_mensal,
                'limite_alunos' => $this->plano->limite_alunos,
            ]),
        ];
    }
}
