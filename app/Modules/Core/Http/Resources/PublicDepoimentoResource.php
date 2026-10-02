<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicDepoimentoResource extends JsonResource
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
        ];
    }
}
