<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TurmaDoProfessorResource',
    title: 'TurmaDoProfessorResource',
    description: 'Turma vista pelo professor (sem valores financeiros)',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'id_curso', type: 'integer', example: 1),
        new OA\Property(property: 'id_nivel', type: 'integer', example: 1),
        new OA\Property(property: 'id_professor', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'descricao', type: 'string', nullable: true, example: 'Violão Iniciante - Sábado'),
        new OA\Property(property: 'observacoes', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'em_andamento'),
        new OA\Property(property: 'maximo_alunos', type: 'integer', example: 10),
        new OA\Property(property: 'data_inicio', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'data_fim', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'alunos_ativos_count', type: 'integer', example: 6),
        new OA\Property(property: 'curso', ref: '#/components/schemas/CursoResource', nullable: true),
        new OA\Property(property: 'nivel', ref: '#/components/schemas/NivelResource', nullable: true),
        new OA\Property(property: 'horarios', type: 'array', items: new OA\Items(ref: '#/components/schemas/TurmaHorarioResource')),
        new OA\Property(property: 'matriculas', type: 'array', items: new OA\Items(ref: '#/components/schemas/AlunoDaTurmaResource')),
    ]
)]
class TurmaDoProfessorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_curso' => $this->id_curso,
            'id_nivel' => $this->id_nivel,
            'id_professor' => $this->id_professor,
            'descricao' => $this->descricao,
            'observacoes' => $this->observacoes,
            'status' => $this->status,
            'maximo_alunos' => $this->maximo_alunos,
            'data_inicio' => $this->data_inicio,
            'data_fim' => $this->data_fim,
            'alunos_ativos_count' => $this->whenHas('alunos_ativos_count'),
            'curso' => new CursoResource($this->whenLoaded('curso')),
            'nivel' => new NivelResource($this->whenLoaded('nivel')),
            'horarios' => TurmaHorarioResource::collection($this->whenLoaded('horarios')),
            'matriculas' => AlunoDaTurmaResource::collection($this->whenLoaded('matriculas')),
        ];
    }
}
