<?php

namespace App\Modules\Financeiro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "CategoriaContaResource",
    title: "Categoria Conta Resource",
    description: "Recurso de Categoria de Conta",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", description: "ID da categoria", example: 1),
        new OA\Property(property: "nome", type: "string", description: "Nome da categoria", example: "Internet"),
        new OA\Property(property: "tipo", type: "string", enum: ["receita", "despesa"], description: "Tipo da categoria", example: "despesa"),
        new OA\Property(property: "descricao", type: "string", description: "Descrição opcional", example: "Plano corporativo mensal", nullable: true)
    ]
)]
class CategoriaContaResource extends JsonResource
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
            'descricao' => $this->descricao,
        ];
    }
}
