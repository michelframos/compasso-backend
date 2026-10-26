<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CertificadoPublicoResource',
    properties: [
        new OA\Property(property: 'valido', type: 'boolean'),
        new OA\Property(property: 'codigo_verificacao', type: 'string'),
        new OA\Property(property: 'aluno', type: 'string', nullable: true),
        new OA\Property(property: 'escola', type: 'string', nullable: true),
        new OA\Property(property: 'curso', type: 'string', nullable: true),
        new OA\Property(property: 'turma', type: 'string', nullable: true),
        new OA\Property(property: 'nivel', type: 'string', nullable: true),
        new OA\Property(property: 'carga_horaria', type: 'number', nullable: true),
        new OA\Property(property: 'emitido_em', type: 'string', nullable: true),
    ]
)]
class CertificadoPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $matricula = $this->matricula;

        return [
            'valido' => true,
            'codigo_verificacao' => $this->codigo_verificacao,
            'aluno' => $matricula?->aluno?->usuario?->nome,
            'escola' => $this->instituicao?->nome_fantasia,
            'curso' => $matricula?->tipo === 'curso'
                ? $matricula->curso?->nome
                : $matricula?->turma?->curso?->nome,
            'turma' => $matricula?->tipo === 'turma' ? ($matricula->turma?->apelido() ?? $matricula->turma?->descricao) : null,
            'nivel' => $matricula?->tipo === 'curso'
                ? $matricula->nivel?->nome
                : $matricula?->turma?->nivel?->nome,
            'carga_horaria' => $this->carga_horaria !== null ? (float) $this->carga_horaria : null,
            'emitido_em' => $this->emitido_em?->format('d/m/Y'),
        ];
    }
}
