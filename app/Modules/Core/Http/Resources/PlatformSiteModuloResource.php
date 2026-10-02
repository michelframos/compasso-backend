<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PlatformSiteModuloResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'descricao', type: 'string'),
        new OA\Property(property: 'recursos', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'icone', type: 'string'),
        new OA\Property(property: 'ordem', type: 'integer'),
        new OA\Property(property: 'aprovado', type: 'boolean'),
        new OA\Property(property: 'aprovado_em', type: 'string', nullable: true),
    ]
)]
class PlatformSiteModuloResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'recursos' => array_values($this->recursos ?? []),
            'icone' => $this->icone,
            'ordem' => $this->ordem,
            'aprovado' => (bool) $this->aprovado,
            'aprovado_em' => $this->aprovado_em,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
