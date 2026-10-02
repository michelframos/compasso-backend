<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DisponibilidadeProfessorResource',
    title: 'DisponibilidadeProfessorResource',
    description: 'Janela semanal de disponibilidade do professor',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'dia_semana', type: 'string', enum: ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo']),
        new OA\Property(property: 'hora_inicio', type: 'string', example: '08:00'),
        new OA\Property(property: 'hora_termino', type: 'string', example: '12:00'),
    ]
)]
class DisponibilidadeProfessorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dia_semana' => $this->dia_semana,
            'hora_inicio' => substr((string) $this->hora_inicio, 0, 5),
            'hora_termino' => substr((string) $this->hora_termino, 0, 5),
        ];
    }
}
