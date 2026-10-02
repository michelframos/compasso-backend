<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ModuloInstituicaoResource',
    description: 'Módulo do catálogo e sua situação na escola atual',
    properties: [
        new OA\Property(property: 'key', type: 'string', example: 'progressao'),
        new OA\Property(property: 'label', type: 'string', example: 'Progressão de nível'),
        new OA\Property(property: 'descricao', type: 'string'),
        new OA\Property(property: 'contratado', type: 'boolean', description: 'Incluído no plano ou liberado pelo trial'),
        new OA\Property(property: 'ativo', type: 'boolean', description: 'Contratado e não desligado pela escola'),
        new OA\Property(property: 'desativavel', type: 'boolean', description: 'A escola pode ligar ou desligar'),
    ]
)]
class ModuloInstituicaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'label' => $this->resource['label'],
            'descricao' => $this->resource['descricao'],
            'contratado' => $this->resource['contratado'],
            'ativo' => $this->resource['ativo'],
            'desativavel' => $this->resource['desativavel'],
        ];
    }
}
