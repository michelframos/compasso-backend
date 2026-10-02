<?php

namespace App\Modules\Instrumentos\Http\Resources;

use App\Modules\Pessoas\Http\Resources\AlunoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "InstrumentoResource",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "nome", type: "string", example: "Violino 4/4"),
        new OA\Property(property: "tipo", type: "string", example: "Corda"),
        new OA\Property(property: "numero_serie", type: "string", nullable: true, example: "SN123456"),
        new OA\Property(property: "status", type: "string", example: "emprestado"),
        new OA\Property(property: "id_aluno", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "data_emprestimo", type: "string", format: "date", nullable: true, example: "2024-03-04"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Corda Mi levemente oxidada"),
    ]
)]
class InstrumentoResource extends JsonResource
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
            'tipo' => $this->tipo,
            'numero_serie' => $this->numero_serie,
            'status' => $this->status,
            'id_aluno' => $this->id_aluno,
            'data_emprestimo' => $this->data_emprestimo,
            'observacoes' => $this->observacoes,
            'aluno' => new AlunoResource($this->whenLoaded('aluno')),
            'contrato_gerado' => $this->id_aluno ? $this->historicos()
                ->where('id_aluno', $this->id_aluno)
                ->where('acao', 'emprestimo')
                ->latest('data')
                ->value('contrato_gerado') : null,
        ];
    }
}
