<?php

namespace App\Modules\Financeiro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ContratoDoAlunoResource',
    title: 'ContratoDoAlunoResource',
    description: 'Contrato gerado para o aluno. O aceite não expõe IP, navegador nem o hash.',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'nome', type: 'string', nullable: true),
        new OA\Property(property: 'contrato_gerado', type: 'string', nullable: true),
        new OA\Property(property: 'aceito', type: 'boolean'),
        new OA\Property(property: 'aceito_em', type: 'string', nullable: true, example: '2026-10-26 17:00'),
    ]
)]
class ContratoDoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->contrato?->nome,
            'contrato_gerado' => $this->contrato_gerado,
            'aceito' => $this->aceito_em !== null,
            'aceito_em' => $this->aceito_em ? substr((string) $this->aceito_em, 0, 16) : null,
        ];
    }
}
