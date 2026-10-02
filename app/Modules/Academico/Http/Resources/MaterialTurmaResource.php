<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "MaterialTurmaResource",
    title: "Material Turma",
    description: "Recurso de Material da Turma",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "titulo", type: "string", example: "Apostila de Violino"),
        new OA\Property(property: "descricao", type: "string", example: "Material de apoio para as primeiras aulas", nullable: true),
        new OA\Property(property: "file_path", type: "string", example: "materiais_turmas/1/apostila.pdf"),
        new OA\Property(property: "file_url", type: "string", example: "http://localhost/storage/materiais_turmas/1/apostila.pdf"),
        new OA\Property(property: "file_type", type: "string", example: "application/pdf"),
        new OA\Property(property: "publico", type: "boolean", example: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2026-02-21 10:00:00", nullable: true)
    ]
)]
class MaterialTurmaResource extends JsonResource
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
            'id_turma' => $this->id_turma,
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'file_path' => $this->file_path,
            'file_url' => $this->file_path ? url('storage/' . $this->file_path) : null,
            'file_type' => $this->file_type,
            'publico' => (bool) $this->publico,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}
