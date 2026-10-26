<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\AulaTurma;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OpcaoReposicaoResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'data', type: 'string', format: 'date'),
        new OA\Property(property: 'hora_inicio', type: 'string'),
        new OA\Property(property: 'hora_termino', type: 'string'),
        new OA\Property(property: 'turma', type: 'string', nullable: true),
    ]
)]
class OpcaoReposicaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AulaTurma $aula */
        $aula = $this->resource;

        return [
            'id' => $aula->id,
            'data' => $aula->data?->toDateString(),
            'hora_inicio' => substr((string) $aula->hora_inicio, 0, 5),
            'hora_termino' => substr((string) $aula->hora_termino, 0, 5),
            'turma' => $aula->turma?->descricao,
        ];
    }
}
