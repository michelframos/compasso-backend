<?php

namespace App\Modules\Financeiro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "ContaResource",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "id_categoria", type: "integer", example: 1),
        new OA\Property(property: "id_aluno", type: "integer", nullable: true, example: 5),
        new OA\Property(property: "id_professor", type: "integer", nullable: true, example: null),
        new OA\Property(property: "id_matricula", type: "integer", nullable: true, example: 10),
        new OA\Property(property: "descricao", type: "string", example: "Mensalidade de Março"),
        new OA\Property(property: "valor", type: "number", format: "float", example: 150.00),
        new OA\Property(property: "data_vencimento", type: "string", format: "date", example: "2026-03-10"),
        new OA\Property(property: "data_pagamento", type: "string", format: "date", nullable: true, example: null),
        new OA\Property(property: "status", type: "string", example: "pendente"),
        new OA\Property(property: "tipo", type: "string", example: "receita"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Fatura gerada automaticamente"),
        new OA\Property(property: "numero_parcela", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "quantidade_parcelas", type: "integer", nullable: true, example: 12),
        new OA\Property(property: "mes_referencia", type: "integer", nullable: true, example: 3),
        new OA\Property(property: "ano_referencia", type: "integer", nullable: true, example: 2026),
        new OA\Property(property: "saldo_devedor", type: "number", format: "float", example: 100.00),
        new OA\Property(
            property: "pagamentos",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/ContaPagamentoResource"),
            nullable: true
        ),
        new OA\Property(property: "categoria", ref: "#/components/schemas/CategoriaContaResource", nullable: true),
        new OA\Property(property: "aluno", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer", example: 5),
            new OA\Property(property: "nome", type: "string", example: "Maria Silva"),
        ]),
        new OA\Property(property: "professor", type: "object", nullable: true, properties: [
            new OA\Property(property: "id", type: "integer", example: 2),
            new OA\Property(property: "nome", type: "string", example: "João Professor"),
        ]),
        new OA\Property(property: "notificar", type: "boolean", example: true)
    ]
)]
class ContaResource extends JsonResource
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
            'id_categoria' => $this->id_categoria,
            'id_aluno' => $this->id_aluno,
            'id_professor' => $this->id_professor,
            'id_matricula' => $this->id_matricula,
            'descricao' => $this->descricao,
            'valor' => $this->valor,
            'data_vencimento' => $this->data_vencimento,
            'data_pagamento' => $this->data_pagamento,
            'status' => $this->status,
            'tipo' => $this->tipo,
            'observacoes' => $this->observacoes,
            'numero_parcela' => $this->numero_parcela,
            'quantidade_parcelas' => $this->quantidade_parcelas,
            'mes_referencia' => $this->mes_referencia,
            'ano_referencia' => $this->ano_referencia,
            'saldo_devedor' => round($this->valor - $this->pagamentos->sum('valor_pago'), 2),
            'pagamentos' => ContaPagamentoResource::collection($this->whenLoaded('pagamentos')),
            'categoria' => new CategoriaContaResource($this->whenLoaded('categoria')),
            'aluno' => $this->whenLoaded('aluno', function () {
                if (! $this->aluno) {
                    return null;
                }

                return [
                    'id' => $this->aluno->id,
                    'nome' => $this->aluno->usuario?->nome,
                ];
            }),
            'professor' => $this->whenLoaded('professor', function () {
                if (! $this->professor) {
                    return null;
                }

                return [
                    'id' => $this->professor->id,
                    'nome' => $this->professor->usuario?->nome,
                ];
            }),
            'notificar' => (bool) $this->notificar,
        ];
    }
}
