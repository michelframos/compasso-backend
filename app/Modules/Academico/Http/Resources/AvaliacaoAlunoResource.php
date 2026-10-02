<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\AvaliacaoAluno;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AvaliacaoAlunoResource',
    title: 'AvaliacaoAlunoResource',
    description: 'Avaliação de um aluno registrada por um professor',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'id_aluno', type: 'integer', example: 5),
        new OA\Property(property: 'id_turma', type: 'integer', nullable: true, description: 'Nulo = aulas individuais', example: 2),
        new OA\Property(property: 'id_professor', type: 'integer', example: 3),
        new OA\Property(property: 'data', type: 'string', format: 'date', example: '2026-09-30'),
        new OA\Property(property: 'tipo', type: 'string', enum: AvaliacaoAluno::TIPOS, example: 'pratica'),
        new OA\Property(property: 'nota', type: 'number', nullable: true, example: 8.5),
        new OA\Property(property: 'conceito', type: 'string', nullable: true, enum: AvaliacaoAluno::CONCEITOS, example: 'bom'),
        new OA\Property(property: 'comentario', type: 'string', nullable: true),
        new OA\Property(property: 'pode_editar', type: 'boolean', description: 'Se o usuário autenticado pode editar/excluir (autor da avaliação)', example: true),
        new OA\Property(property: 'aluno', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 5),
            new OA\Property(property: 'nome', type: 'string', example: 'Bruno Souza'),
        ]),
        new OA\Property(property: 'professor', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 3),
            new OA\Property(property: 'nome', type: 'string', example: 'Ana Lima'),
        ]),
        new OA\Property(property: 'turma', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 2),
            new OA\Property(property: 'descricao', type: 'string', nullable: true),
            new OA\Property(property: 'curso', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string')]),
            new OA\Property(property: 'nivel', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string')]),
        ]),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class AvaliacaoAlunoResource extends JsonResource
{
    /** O campo `data` (data da avaliação) impede o Laravel de aplicar o envelope `data` automaticamente. */
    public function toResponse($request)
    {
        return response()->json(
            ['data' => $this->resolve($request)],
            $this->resource->wasRecentlyCreated ? 201 : 200
        );
    }

    public function toArray(Request $request): array
    {
        $turma = $this->turma;

        return [
            'id' => $this->id,
            'id_aluno' => $this->id_aluno,
            'id_turma' => $this->id_turma,
            'id_professor' => $this->id_professor,
            'data' => $this->data?->format('Y-m-d'),
            'tipo' => $this->tipo,
            'nota' => $this->nota !== null ? (float) $this->nota : null,
            'conceito' => $this->conceito,
            'comentario' => $this->comentario,
            'pode_editar' => (bool) $request->user()?->can('update', $this->resource),
            'aluno' => $this->aluno ? ['id' => $this->aluno->id, 'nome' => $this->aluno->usuario?->nome] : null,
            'professor' => $this->professor ? [
                'id' => $this->professor->id,
                'nome' => $this->professor->usuario?->nome,
            ] : null,
            'turma' => $turma ? [
                'id' => $turma->id,
                'descricao' => $turma->descricao,
                'curso' => $turma->curso ? ['nome' => $turma->curso->nome] : null,
                'nivel' => $turma->nivel ? ['nome' => $turma->nivel->nome] : null,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
