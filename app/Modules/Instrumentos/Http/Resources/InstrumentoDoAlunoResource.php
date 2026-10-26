<?php

namespace App\Modules\Instrumentos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'InstrumentoDoAlunoResource',
    title: 'InstrumentoDoAlunoResource',
    description: 'Instrumento emprestado ao aluno, sem observações internas.',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'tipo', type: 'string'),
        new OA\Property(property: 'numero_serie', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'data_emprestimo', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'data_prevista_devolucao', type: 'string', format: 'date', nullable: true),
    ]
)]
class InstrumentoDoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'tipo' => $this->tipo,
            'numero_serie' => $this->numero_serie,
            'status' => $this->status,
            'data_emprestimo' => $this->data($this->data_emprestimo),
            'data_prevista_devolucao' => $this->data($this->data_prevista_devolucao),
        ];
    }

    private function data(mixed $valor): ?string
    {
        return $valor ? substr((string) $valor, 0, 10) : null;
    }
}
