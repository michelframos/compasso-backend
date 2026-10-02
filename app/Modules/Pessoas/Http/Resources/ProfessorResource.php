<?php

namespace App\Modules\Pessoas\Http\Resources;

use App\Modules\Core\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "ProfessorResource",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "id_usuario", type: "integer", example: 1),
        new OA\Property(property: "valor_hora_aula", type: "number", format: "float", example: 50.00),
        new OA\Property(property: "comissao", type: "number", format: "float", example: 10.00),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Professor de Piano"),
        new OA\Property(property: "usuario", ref: "#/components/schemas/UserResource", nullable: true)
    ]
)]
class ProfessorResource extends JsonResource
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
            'id_usuario' => $this->id_usuario,
            'valor_hora_aula' => $this->valor_hora_aula,
            'comissao' => $this->comissao,
            'observacoes' => $this->observacoes,
            'usuario' => new UserResource($this->whenLoaded('usuario')),
        ];
    }
}
