<?php

namespace App\Modules\Financeiro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "ContaPagamentoResource",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "id_conta", type: "integer", example: 1),
        new OA\Property(property: "valor_pago", type: "number", format: "float", example: 50.00),
        new OA\Property(property: "data_pagamento", type: "string", format: "date", example: "2026-03-10"),
        new OA\Property(property: "forma_pagamento", type: "string", example: "PIX"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Recibo 1234"),
        new OA\Property(property: "conta", ref: "#/components/schemas/ContaResource", nullable: true)
    ]
)]
class ContaPagamentoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_conta' => $this->id_conta,
            'valor_pago' => $this->valor_pago,
            'data_pagamento' => $this->data_pagamento,
            'forma_pagamento' => $this->forma_pagamento,
            'observacoes' => $this->observacoes,
            'conta' => new ContaResource($this->whenLoaded('conta')),
        ];
    }
}
