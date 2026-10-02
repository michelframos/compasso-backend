<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PlatformDepoimentoResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'cargo', type: 'string'),
        new OA\Property(property: 'escola', type: 'string'),
        new OA\Property(property: 'conteudo', type: 'string'),
        new OA\Property(property: 'avatar_url', type: 'string', nullable: true),
        new OA\Property(property: 'ordem', type: 'integer'),
        new OA\Property(property: 'aprovado', type: 'boolean'),
        new OA\Property(property: 'aprovado_em', type: 'string', nullable: true),
    ]
)]
class PlatformDepoimentoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cargo' => $this->cargo,
            'escola' => $this->escola,
            'conteudo' => $this->conteudo,
            'avatar_url' => $this->avatar_url,
            'ordem' => $this->ordem,
            'aprovado' => (bool) $this->aprovado,
            'aprovado_em' => $this->aprovado_em,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
