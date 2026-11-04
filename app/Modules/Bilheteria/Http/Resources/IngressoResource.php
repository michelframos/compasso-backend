<?php

namespace App\Modules\Bilheteria\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngressoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_espetaculo' => $this->id_espetaculo,
            'id_template_ingresso' => $this->id_template_ingresso,
            'numero' => $this->numero,
            'codigo_qr' => $this->codigo_qr,
            'valor' => $this->valor,
            'status' => $this->status,
            'status_rotulo' => $this->rotuloStatus(),
            'cortesia' => (bool) $this->cortesia,
            'status_pagamento' => $this->status_pagamento,
            'id_conta' => $this->id_conta,
            'id_cupom' => $this->id_cupom,
            'comprador_nome' => $this->comprador_nome,
            'comprador_documento' => $this->comprador_documento,
            'utilizado_em' => $this->utilizado_em?->toISOString(),
            'espetaculo' => $this->whenLoaded('espetaculo', fn () => [
                'id' => $this->espetaculo->id,
                'titulo' => $this->espetaculo->titulo,
                'data_evento' => $this->espetaculo->data_evento?->format('Y-m-d'),
                'hora_evento' => $this->espetaculo->hora_evento
                    ? substr((string) $this->espetaculo->hora_evento, 0, 5)
                    : null,
                'local' => $this->espetaculo->local,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
