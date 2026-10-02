<?php

namespace App\Modules\Espetaculos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "EspetaculoResource",
    title: "Espetaculo",
    description: "Recurso de Espetaculo",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "titulo", type: "string", example: "Concerto de Inverno"),
        new OA\Property(property: "data_evento", type: "string", format: "date", example: "2026-07-15"),
        new OA\Property(property: "local", type: "string", example: "Teatro Municipal", nullable: true),
        new OA\Property(property: "status", type: "string", example: "planejamento"),
        new OA\Property(property: "observacoes", type: "string", example: "Primeiro espetáculo da temporada", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2026-02-21 10:00:00", nullable: true),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2026-02-21 10:00:00", nullable: true),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", example: "2026-02-25 15:30:00", nullable: true)
    ]
)]
class EspetaculoResource extends JsonResource
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
            'titulo' => $this->titulo,
            'data_evento' => $this->data_evento,
            'local' => $this->local,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            'deleted_at' => $this->deleted_at ? $this->deleted_at->toDateTimeString() : null,
        ];
    }
}
