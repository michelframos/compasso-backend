<?php

namespace App\Modules\Bilheteria\Http\Resources;

use App\Modules\Bilheteria\Models\TemplateIngresso;
use App\Modules\Bilheteria\Support\LayoutIngresso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TemplateIngressoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $layout = LayoutIngresso::parse($this->conteudo);
        $disco = Storage::disk(TemplateIngresso::DISCO_FUNDO);

        foreach ($layout['elementos'] as &$elemento) {
            if (($elemento['tipo'] ?? '') === 'imagem') {
                $src = (string) ($elemento['src'] ?? '');
                $elemento['url'] = $src !== '' ? $disco->url($src) : null;
            }
        }
        unset($elemento);

        $px = $this->dimensoesEmPixels();

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'largura' => $this->largura,
            'altura' => $this->altura,
            'unidade' => $this->unidade,
            'largura_px' => $px['largura'],
            'altura_px' => $px['altura'],
            'conteudo' => LayoutIngresso::encode($layout),
            'layout' => $layout,
            'fundo_path' => $this->fundo_path,
            'fundo_url' => $this->fundoUrl(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
