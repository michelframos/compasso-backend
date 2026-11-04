<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\JustificativaFalta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'JustificativaFaltaResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'tipo', type: 'string', enum: JustificativaFalta::TIPOS),
        new OA\Property(property: 'motivo', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: JustificativaFalta::STATUS),
        new OA\Property(property: 'anexo_url', type: 'string', nullable: true),
        new OA\Property(property: 'aluno', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'aula', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'data', type: 'string', format: 'date'),
            new OA\Property(property: 'hora_inicio', type: 'string'),
            new OA\Property(property: 'hora_termino', type: 'string'),
            new OA\Property(property: 'turma', type: 'string', nullable: true),
        ]),
    ]
)]
class JustificativaFaltaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $aula = $this->aula;

        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'motivo' => $this->motivo,
            'status' => $this->status,
            'anexo_url' => $this->anexo ? Storage::disk('public')->url($this->anexo) : null,
            'aluno' => [
                'id' => $this->id_aluno,
                'nome' => $this->aluno?->usuario?->nome,
            ],
            'aula' => $aula ? [
                'id' => $aula->id,
                'data' => $aula->data?->toDateString(),
                'hora_inicio' => substr((string) $aula->hora_inicio, 0, 5),
                'hora_termino' => substr((string) $aula->hora_termino, 0, 5),
                'turma' => $aula->turma?->descricao,
            ] : null,
        ];
    }
}
