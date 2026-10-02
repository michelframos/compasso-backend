<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "NivelResource",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "nome", type: "string", example: "Iniciante"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Nível para alunos sem experiência prévia"),
        new OA\Property(property: "curso_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "curso", ref: "#/components/schemas/CursoResource", nullable: true),
        new OA\Property(property: "ordem", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "idade_minima", type: "integer", nullable: true, example: 3),
        new OA\Property(property: "idade_maxima", type: "integer", nullable: true, example: 6),
        new OA\Property(property: "cor_identificacao", type: "string", nullable: true, example: "#FFB6C1"),
        new OA\Property(property: "expectativas_aprendizado", type: "string", nullable: true, example: "Desenvolver.")
    ]
)]
class NivelResource extends JsonResource
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
            'observacoes' => $this->observacoes,
            'curso_id' => $this->curso_id,
            'curso' => new CursoResource($this->whenLoaded('curso')),
            'ordem' => $this->ordem,
            'idade_minima' => $this->idade_minima,
            'idade_maxima' => $this->idade_maxima,
            'cor_identificacao' => $this->cor_identificacao,
            'expectativas_aprendizado' => $this->expectativas_aprendizado,
        ];
    }
}
