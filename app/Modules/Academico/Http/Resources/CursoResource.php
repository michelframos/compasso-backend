<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Financeiro\Http\Resources\ContratoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "CursoResource",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "nome", type: "string", example: "Violino"),
        new OA\Property(property: "descricao", type: "string", nullable: true, example: "Curso de violino para iniciantes")
    ]
)]
class CursoResource extends JsonResource
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
            'descricao' => $this->descricao,
            'contrato_id' => $this->contrato_id,
            'contrato' => new ContratoResource($this->whenLoaded('contrato')),
        ];
    }
}
