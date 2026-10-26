<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ReposicaoDoAlunoResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'id_aula_turma_perdida', type: 'integer'),
        new OA\Property(property: 'id_aula_turma_destino', type: 'integer', nullable: true),
        new OA\Property(property: 'motivo', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['pendente', 'aprovada', 'recusada']),
        new OA\Property(property: 'destino', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'data', type: 'string', format: 'date'),
            new OA\Property(property: 'hora_inicio', type: 'string'),
            new OA\Property(property: 'hora_termino', type: 'string'),
            new OA\Property(property: 'turma', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'decidido_em', type: 'string', nullable: true),
    ]
)]
class ReposicaoDoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $destino = $this->destino;

        return [
            'id' => $this->id,
            'id_aula_turma_perdida' => $this->id_aula_turma_perdida,
            'id_aula_turma_destino' => $this->id_aula_turma_destino,
            'motivo' => $this->motivo,
            'status' => $this->status,
            'destino' => $destino ? [
                'id' => $destino->id,
                'data' => $destino->data?->toDateString(),
                'hora_inicio' => substr((string) $destino->hora_inicio, 0, 5),
                'hora_termino' => substr((string) $destino->hora_termino, 0, 5),
                'turma' => $destino->turma?->descricao,
            ] : null,
            'decidido_em' => $this->decidido_em ? substr((string) $this->decidido_em, 0, 16) : null,
        ];
    }
}
