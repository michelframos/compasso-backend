<?php

namespace App\Modules\Espetaculos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "ApresentacaoAlunoResource",
    description: "Recurso de Apresentação Aluno",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer"),
        new OA\Property(property: "id_apresentacao", type: "integer"),
        new OA\Property(property: "id_aluno", type: "integer"),
        new OA\Property(property: "tamanho_figurino", type: "string", nullable: true),
        new OA\Property(property: "valor_figurino", type: "number", format: "float", nullable: true),
        new OA\Property(property: "pago_figurino", type: "boolean"),
        new OA\Property(property: "recebeu_figurino", type: "boolean"),
        new OA\Property(property: "fatura_gerada", type: "boolean"),
        new OA\Property(property: "presenca_ensaio_geral", type: "boolean"),
        new OA\Property(property: "apresentacao", ref: "#/components/schemas/ApresentacaoResource", nullable: true),
        new OA\Property(property: "aluno", type: "object", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", nullable: true)
    ]
)]
class ApresentacaoAlunoResource extends JsonResource
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
            'id_apresentacao' => $this->id_apresentacao,
            'id_aluno' => $this->id_aluno,
            'tamanho_figurino' => $this->tamanho_figurino,
            'valor_figurino' => $this->valor_figurino,
            'pago_figurino' => (bool)$this->pago_figurino,
            'recebeu_figurino' => (bool)$this->recebeu_figurino,
            'fatura_gerada' => (bool)$this->fatura_gerada,
            'presenca_ensaio_geral' => (bool)$this->presenca_ensaio_geral,
            'apresentacao' => new ApresentacaoResource($this->whenLoaded('apresentacao')),
            // we assume an AlunoResource will be used if needed, or we just return the array. Let's return relation if loaded.
            'aluno' => $this->whenLoaded('aluno'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
