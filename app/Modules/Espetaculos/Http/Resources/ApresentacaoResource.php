<?php

namespace App\Modules\Espetaculos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "ApresentacaoResource",
    title: "Apresentacao",
    description: "Recurso de Apresentacao",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "id_espetaculo", type: "integer", example: 1),
        new OA\Property(property: "id_turma", type: "integer", example: 1, nullable: true),
        new OA\Property(property: "titulo_musica", type: "string", example: "Ode to Joy", nullable: true),
        new OA\Property(property: "ordem_entrada", type: "integer", example: 1, nullable: true),
        new OA\Property(property: "duracao_estimada", type: "string", example: "00:05:00", nullable: true),
        new OA\Property(
            property: "espetaculo",
            ref: "#/components/schemas/EspetaculoResource",
            nullable: true
        ),
        new OA\Property(
            property: "turma",
            type: "object",
            nullable: true,
            properties: [
                new OA\Property(property: "id", type: "integer"),
                new OA\Property(property: "nome", type: "string")
            ]
        ),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2026-02-21 10:00:00", nullable: true),
        new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2026-02-21 10:00:00", nullable: true),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", example: "2026-02-25 10:00:00", nullable: true)
    ]
)]
class ApresentacaoResource extends JsonResource
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
            'id_espetaculo' => $this->id_espetaculo,
            'id_turma' => $this->id_turma,
            'titulo_musica' => $this->titulo_musica,
            'ordem_entrada' => $this->ordem_entrada,
            'duracao_estimada' => $this->duracao_estimada,
            'espetaculo' => new EspetaculoResource($this->whenLoaded('espetaculo')),
            'turma' => $this->whenLoaded('turma', function () {
                return [
                    'id' => $this->turma->id,
                    'nome' => $this->turma->nome_turma ?? $this->turma->nome ?? 'Turma',
                ];
            }),
            'participantes' => ApresentacaoAlunoResource::collection($this->whenLoaded('alunos')),
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            'deleted_at' => $this->deleted_at ? $this->deleted_at->toDateTimeString() : null,
        ];
    }
}
