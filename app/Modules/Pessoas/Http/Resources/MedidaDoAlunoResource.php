<?php

namespace App\Modules\Pessoas\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MedidaDoAlunoResource',
    title: 'MedidaDoAlunoResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'id_aluno', type: 'integer'),
        new OA\Property(property: 'medida_torax', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'medida_cintura', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'medida_quadril', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'medida_altura', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'ativo', type: 'boolean'),
        new OA\Property(property: 'criado_em', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class MedidaDoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_aluno' => $this->id_aluno,
            'medida_torax' => $this->numero($this->medida_torax),
            'medida_cintura' => $this->numero($this->medida_cintura),
            'medida_quadril' => $this->numero($this->medida_quadril),
            'medida_altura' => $this->numero($this->medida_altura),
            'ativo' => (bool) $this->ativo,
            'criado_em' => $this->criado_em,
        ];
    }

    private function numero(mixed $valor): ?float
    {
        return $valor === null ? null : (float) $valor;
    }
}
