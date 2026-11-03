<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\TemplateCertificado;
use App\Modules\Academico\Support\LayoutCertificado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TemplateCertificadoResource',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'conteudo', type: 'string', description: 'JSON do layout version 1'),
        new OA\Property(property: 'layout', type: 'object'),
        new OA\Property(property: 'fundo_path', type: 'string', nullable: true),
        new OA\Property(property: 'fundo_url', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', nullable: true),
    ]
)]
class TemplateCertificadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $layout = LayoutCertificado::parse($this->conteudo);
        $layout = $this->enriquecerUrls($layout);

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'conteudo' => LayoutCertificado::encode($layout),
            'layout' => $layout,
            'fundo_path' => $this->fundo_path,
            'fundo_url' => $this->fundoUrl(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @param  array{version: int, elementos: list<array<string, mixed>>}  $layout
     * @return array{version: int, elementos: list<array<string, mixed>>}
     */
    private function enriquecerUrls(array $layout): array
    {
        $disco = Storage::disk(TemplateCertificado::DISCO_FUNDO);

        foreach ($layout['elementos'] as &$elemento) {
            if (($elemento['tipo'] ?? '') !== 'imagem') {
                continue;
            }
            $src = (string) ($elemento['src'] ?? '');
            $elemento['url'] = $src !== '' ? $disco->url($src) : null;
        }
        unset($elemento);

        return $layout;
    }
}
