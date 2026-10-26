<?php

namespace App\Modules\Instrumentos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'HistoricoInstrumentoDoAlunoResource',
    title: 'HistoricoInstrumentoDoAlunoResource',
    description: 'Movimento de empréstimo, devolução ou manutenção do aluno. Sem observações internas nem o termo gerado.',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'acao', type: 'string', enum: ['emprestimo', 'devolucao', 'manutencao']),
        new OA\Property(property: 'data', type: 'string', example: '2026-10-01 09:00'),
        new OA\Property(property: 'instrumento', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
            new OA\Property(property: 'tipo', type: 'string'),
        ]),
    ]
)]
class HistoricoInstrumentoDoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $instrumento = $this->instrumento;

        return [
            'id' => $this->id,
            'acao' => $this->acao,
            'data' => substr((string) $this->data, 0, 16),
            'instrumento' => $instrumento ? [
                'id' => $instrumento->id,
                'nome' => $instrumento->nome,
                'tipo' => $instrumento->tipo,
            ] : null,
        ];
    }
}
