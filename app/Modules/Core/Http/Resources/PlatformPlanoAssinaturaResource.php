<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PlatformPlanoAssinaturaResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'descricao', type: 'string', nullable: true),
        new OA\Property(property: 'preco_mensal', type: 'number'),
        new OA\Property(property: 'limite_alunos', type: 'integer', nullable: true),
        new OA\Property(property: 'modulos', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'ativo', type: 'boolean'),
        new OA\Property(property: 'instituicoes_count', type: 'integer', nullable: true),
    ]
)]
class PlatformPlanoAssinaturaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'preco_mensal' => $this->preco_mensal,
            'limite_alunos' => $this->limite_alunos,
            'modulos' => array_values($this->modulos ?? []),
            'ativo' => $this->ativo,
            'instituicoes_count' => $this->whenCounted('instituicoes'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
