<?php

namespace App\Modules\Espetaculos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EnsaioResource',
    title: 'EnsaioResource',
    description: 'Ensaio de uma apresentação',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'id_apresentacao', type: 'integer', example: 3),
        new OA\Property(property: 'id_professor', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'data', type: 'string', format: 'date', example: '2026-11-10'),
        new OA\Property(property: 'hora_inicio', type: 'string', example: '14:00'),
        new OA\Property(property: 'hora_termino', type: 'string', example: '15:30'),
        new OA\Property(property: 'local', type: 'string', nullable: true, example: 'Sala 2'),
        new OA\Property(property: 'observacoes', type: 'string', nullable: true),
        new OA\Property(property: 'pode_editar', type: 'boolean', description: 'Se o usuário autenticado pode editar/excluir', example: true),
        new OA\Property(property: 'professor', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
        ]),
        new OA\Property(property: 'apresentacao', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'titulo_musica', type: 'string', nullable: true),
            new OA\Property(property: 'turma', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'descricao', type: 'string', nullable: true),
            ]),
            new OA\Property(property: 'espetaculo', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'titulo', type: 'string'),
                new OA\Property(property: 'data_evento', type: 'string', format: 'date'),
            ]),
        ]),
    ]
)]
class EnsaioResource extends JsonResource
{
    /** O campo `data` (data do ensaio) impede o Laravel de aplicar o envelope `data` automaticamente. */
    public function toResponse($request)
    {
        return response()->json(
            ['data' => $this->resolve($request)],
            $this->resource->wasRecentlyCreated ? 201 : 200
        );
    }

    public function toArray(Request $request): array
    {
        $apresentacao = $this->relationLoaded('apresentacao') ? $this->apresentacao : null;

        return [
            'id' => $this->id,
            'id_apresentacao' => $this->id_apresentacao,
            'id_professor' => $this->id_professor,
            'data' => $this->data?->format('Y-m-d'),
            'hora_inicio' => substr((string) $this->hora_inicio, 0, 5),
            'hora_termino' => substr((string) $this->hora_termino, 0, 5),
            'local' => $this->local,
            'observacoes' => $this->observacoes,
            'pode_editar' => (bool) $request->user()?->can('update', $this->resource),
            'professor' => $this->professor ? [
                'id' => $this->professor->id,
                'nome' => $this->professor->usuario?->nome,
            ] : null,
            'apresentacao' => $apresentacao ? [
                'id' => $apresentacao->id,
                'titulo_musica' => $apresentacao->titulo_musica,
                'turma' => $apresentacao->turma ? [
                    'id' => $apresentacao->turma->id,
                    'descricao' => $apresentacao->turma->descricao,
                ] : null,
                'espetaculo' => $apresentacao->espetaculo ? [
                    'id' => $apresentacao->espetaculo->id,
                    'titulo' => $apresentacao->espetaculo->titulo,
                    'data_evento' => substr((string) $apresentacao->espetaculo->data_evento, 0, 10),
                ] : null,
            ] : null,
        ];
    }
}
