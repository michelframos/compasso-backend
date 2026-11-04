<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SolicitacaoReposicaoResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'motivo', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['pendente', 'aprovada', 'recusada']),
        new OA\Property(property: 'id_aula_turma_destino', type: 'integer', nullable: true),
        new OA\Property(property: 'aluno', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'perdida', type: 'object', nullable: true),
        new OA\Property(property: 'destino', type: 'object', nullable: true),
    ]
)]
class SolicitacaoReposicaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'motivo' => $this->motivo,
            'status' => $this->status,
            'id_aula_turma_destino' => $this->id_aula_turma_destino,
            'aluno' => [
                'id' => $this->id_aluno,
                'nome' => $this->aluno?->usuario?->nome,
            ],
            'perdida' => $this->aulaResumo($this->perdida),
            'destino' => $this->aulaResumo($this->destino),
        ];
    }

    private function aulaResumo(mixed $aula): ?array
    {
        if ($aula === null) {
            return null;
        }

        return [
            'id' => $aula->id,
            'data' => $aula->data?->toDateString(),
            'hora_inicio' => substr((string) $aula->hora_inicio, 0, 5),
            'hora_termino' => substr((string) $aula->hora_termino, 0, 5),
            'turma' => $aula->turma?->descricao,
        ];
    }
}
