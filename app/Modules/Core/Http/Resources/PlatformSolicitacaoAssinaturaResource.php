<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformSolicitacaoAssinaturaResource extends JsonResource
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
            'instituicao' => $this->whenLoaded('instituicao', fn () => [
                'id' => $this->instituicao->id,
                'slug' => $this->instituicao->slug,
                'nome_fantasia' => $this->instituicao->nome_fantasia,
                'assinatura_status' => $this->instituicao->assinatura_status,
                'trial_ends_at' => $this->instituicao->trial_ends_at?->toIso8601String(),
            ]),
            'plano' => $this->whenLoaded('plano', fn () => [
                'id' => $this->plano->id,
                'slug' => $this->plano->slug,
                'nome' => $this->plano->nome,
                'preco' => (float) $this->plano->preco_mensal,
                'limite_alunos' => $this->plano->limite_alunos,
            ]),
            'usuario' => $this->whenLoaded('usuario', fn () => [
                'id' => $this->usuario->id,
                'nome' => $this->usuario->nome,
                'email' => $this->usuario->email,
            ]),
            'aprovado_por' => $this->whenLoaded('aprovadoPor', function () {
                if ($this->aprovadoPor === null) {
                    return null;
                }

                return [
                    'id' => $this->aprovadoPor->id,
                    'nome' => $this->aprovadoPor->nome,
                    'email' => $this->aprovadoPor->email,
                ];
            }),
        ];
    }
}
