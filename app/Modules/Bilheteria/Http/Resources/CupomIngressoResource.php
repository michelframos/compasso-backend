<?php

namespace App\Modules\Bilheteria\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CupomIngressoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_espetaculo' => $this->id_espetaculo,
            'codigo' => $this->codigo,
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'ativo' => (bool) $this->ativo,
            'valido_de' => $this->valido_de?->format('Y-m-d'),
            'valido_ate' => $this->valido_ate?->format('Y-m-d'),
            'uso_maximo' => $this->uso_maximo,
            'usos' => $this->usos,
            'espetaculo' => $this->whenLoaded('espetaculo', fn () => [
                'id' => $this->espetaculo->id,
                'titulo' => $this->espetaculo->titulo,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
