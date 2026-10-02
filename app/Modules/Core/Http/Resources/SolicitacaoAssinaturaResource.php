<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitacaoAssinaturaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'observacao' => $this->observacao,
            'aprovado_em' => $this->aprovado_em?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'plano' => $this->whenLoaded('plano', fn () => [
                'id' => $this->plano->id,
                'slug' => $this->plano->slug,
                'nome' => $this->plano->nome,
                'preco' => (float) $this->plano->preco_mensal,
                'limite_alunos' => $this->plano->limite_alunos,
            ]),
        ];
    }
}
