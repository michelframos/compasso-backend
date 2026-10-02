<?php

namespace App\Modules\Comercial\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "LeadResource",
    title: "Lead",
    description: "Recurso de Lead",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "nome", type: "string", example: "João da Silva"),
        new OA\Property(property: "email", type: "string", example: "joao@example.com", nullable: true),
        new OA\Property(property: "telefone", type: "string", example: "(11) 98765-4321", nullable: true),
        new OA\Property(property: "status", type: "string", example: "novo"),
        new OA\Property(property: "observacoes", type: "string", example: "Interesse em aulas de violão", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2026-02-21 10:00:00", nullable: true),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", example: "2026-02-21 15:30:00", nullable: true)
    ]
)]
class LeadResource extends JsonResource
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
            'nome' => $this->nome,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'deleted_at' => $this->deleted_at ? $this->deleted_at->toDateTimeString() : null,
        ];
    }
}
