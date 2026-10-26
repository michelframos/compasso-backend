<?php

namespace App\Modules\Pessoas\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DependenteResource',
    title: 'Dependente',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'nome', type: 'string', example: 'Ana Lima'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'parentesco', type: 'string', nullable: true, example: 'mãe'),
    ]
)]
class DependenteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->usuario?->nome,
            'email' => $this->usuario?->email,
            'parentesco' => $this->pivot?->parentesco,
        ];
    }
}
