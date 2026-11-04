<?php

namespace App\Modules\Financeiro\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ContratoDoAlunoResource',
    title: 'ContratoDoAlunoResource',
    description: 'Contrato vinculado ao aluno (avulso, matrícula, empréstimo ativo ou espetáculo).',
    properties: [
        new OA\Property(property: 'tipo', type: 'string', enum: ['avulso', 'matricula', 'instrumento', 'espetaculo']),
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'nome', type: 'string', nullable: true),
        new OA\Property(property: 'descricao', type: 'string', nullable: true),
        new OA\Property(property: 'contrato_gerado', type: 'string', nullable: true),
    ]
)]
class ContratoDoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = is_array($this->resource) ? $this->resource : [
            'tipo' => 'avulso',
            'id' => $this->id,
            'nome' => $this->contrato?->nome,
            'descricao' => 'Contrato avulso',
            'contrato_gerado' => $this->contrato_gerado,
        ];

        return [
            'tipo' => $item['tipo'],
            'id' => $item['id'],
            'nome' => $item['nome'],
            'descricao' => $item['descricao'] ?? null,
            'contrato_gerado' => $item['contrato_gerado'],
        ];
    }
}
