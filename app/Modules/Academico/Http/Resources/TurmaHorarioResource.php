<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "TurmaHorarioResource",
    title: "TurmaHorarioResource",
    description: "Recurso de Horário da Turma",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "dia_semana", type: "string", example: "terca"),
        new OA\Property(property: "hora_inicio", type: "string", example: "14:00"),
        new OA\Property(property: "hora_termino", type: "string", example: "16:00"),
    ]
)]
class TurmaHorarioResource extends JsonResource
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
            'dia_semana' => $this->dia_semana,
            'hora_inicio' => $this->hora_inicio,
            'hora_termino' => $this->hora_termino,
        ];
    }
}

