<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CertificadoDoAlunoResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'codigo_verificacao', type: 'string'),
        new OA\Property(property: 'emitido_em', type: 'string'),
        new OA\Property(property: 'carga_horaria', type: 'number'),
        new OA\Property(property: 'curso', type: 'string', nullable: true),
        new OA\Property(property: 'turma', type: 'string', nullable: true),
        new OA\Property(property: 'nivel', type: 'string', nullable: true),
    ]
)]
class CertificadoDoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $matricula = $this->matricula;

        return [
            'id' => $this->id,
            'codigo_verificacao' => $this->codigo_verificacao,
            'emitido_em' => $this->emitido_em ? substr((string) $this->emitido_em, 0, 16) : null,
            'carga_horaria' => $this->carga_horaria !== null ? (float) $this->carga_horaria : null,
            'curso' => $matricula?->tipo === 'curso'
                ? $matricula->curso?->nome
                : $matricula?->turma?->curso?->nome,
            'turma' => $matricula?->tipo === 'turma' ? ($matricula->turma?->apelido() ?? $matricula->turma?->descricao) : null,
            'nivel' => $matricula?->tipo === 'curso'
                ? $matricula->nivel?->nome
                : $matricula?->turma?->nivel?->nome,
        ];
    }
}
