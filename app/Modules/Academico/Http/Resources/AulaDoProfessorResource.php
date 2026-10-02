<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AulaDoProfessorResource',
    title: 'AulaDoProfessorResource',
    description: 'Aula vista pelo professor (sem snapshots financeiros), com os alunos ativos da turma para a chamada',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'id_turma', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'id_professor', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'data', type: 'string', format: 'date', example: '2026-10-01'),
        new OA\Property(property: 'hora_inicio', type: 'string', example: '14:00:00'),
        new OA\Property(property: 'hora_termino', type: 'string', example: '15:00:00'),
        new OA\Property(property: 'status', type: 'string', nullable: true, example: 'agendada'),
        new OA\Property(property: 'tipo', type: 'string', example: 'regular'),
        new OA\Property(property: 'conteudo_dado', type: 'string', nullable: true),
        new OA\Property(property: 'presencas_count', type: 'integer', example: 0),
        new OA\Property(property: 'turma', ref: '#/components/schemas/TurmaDoProfessorResource', nullable: true),
        new OA\Property(property: 'aluno_especifico', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 3),
            new OA\Property(property: 'usuario', type: 'object', properties: [
                new OA\Property(property: 'nome', type: 'string', example: 'Carla Dias'),
            ]),
        ]),
        new OA\Property(property: 'presencas', type: 'array', description: 'Presenças já lançadas (apenas no detalhe da aula)', items: new OA\Items(properties: [
            new OA\Property(property: 'id_aluno', type: 'integer', example: 5),
            new OA\Property(property: 'status', type: 'string', enum: ['presente', 'ausente', 'justificado'], example: 'presente'),
            new OA\Property(property: 'observacao', type: 'string', nullable: true),
        ])),
    ]
)]
class AulaDoProfessorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_turma' => $this->id_turma,
            'id_professor' => $this->id_professor,
            'data' => $this->data?->format('Y-m-d'),
            'hora_inicio' => $this->hora_inicio,
            'hora_termino' => $this->hora_termino,
            'status' => $this->status,
            'tipo' => $this->tipo,
            'conteudo_dado' => $this->conteudo_dado,
            'presencas_count' => $this->whenCounted('presencas'),
            'turma' => new TurmaDoProfessorResource($this->whenLoaded('turma')),
            'aluno_especifico' => $this->whenLoaded('aluno_especifico', fn () => $this->aluno_especifico ? [
                'id' => $this->aluno_especifico->id,
                'usuario' => ['nome' => $this->aluno_especifico->usuario?->nome],
            ] : null),
            'presencas' => $this->whenLoaded('presencas', fn () => $this->presencas->map(fn ($presenca) => [
                'id_aluno' => $presenca->id_aluno,
                'status' => $presenca->status,
                'observacao' => $presenca->observacao,
            ])->values()),
        ];
    }
}
