<?php

namespace App\Modules\Bilheteria\Http\Resources;

use App\Modules\Bilheteria\Models\TemplateIngresso;
use App\Modules\Bilheteria\Services\IngressoPdfService;
use App\Modules\Bilheteria\Services\QrCodeIngressoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class IngressoPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $espetaculo = $this->espetaculo;
        $template = $this->template;
        $pixels = $template?->dimensoesEmPixels() ?? [
            'largura' => (int) round(15 * 96 / 2.54),
            'altura' => (int) round(7 * 96 / 2.54),
        ];

        $html = filled($this->conteudo_gerado)
            ? app(IngressoPdfService::class)->normalizarConteudoHtml((string) $this->conteudo_gerado)
            : null;

        return [
            'numero' => $this->numero,
            'codigo_qr' => $this->codigo_qr,
            'status' => $this->status,
            'status_rotulo' => $this->rotuloStatus(),
            'cortesia' => (bool) $this->cortesia,
            'comprador_nome' => $this->comprador_nome,
            'valor' => $this->valor !== null ? (float) $this->valor : null,
            'tem_template' => filled($this->conteudo_gerado),
            'escola' => $this->instituicao?->nome_fantasia,
            'qr_data_uri' => app(QrCodeIngressoService::class)->dataUri((string) $this->codigo_qr),
            'layout' => [
                'html' => $html,
                'largura_px' => $pixels['largura'],
                'altura_px' => $pixels['altura'],
                'fundo_url' => $this->fundoUrlPublica(),
            ],
            'espetaculo' => $espetaculo ? [
                'titulo' => $espetaculo->titulo,
                'data_evento' => $espetaculo->data_evento?->format('d/m/Y'),
                'hora_evento' => $espetaculo->hora_evento
                    ? substr((string) $espetaculo->hora_evento, 0, 5)
                    : null,
                'local' => $espetaculo->local,
            ] : null,
        ];
    }

    private function fundoUrlPublica(): ?string
    {
        $caminho = $this->fundo_path;
        if (! $caminho) {
            return null;
        }

        $disco = Storage::disk(TemplateIngresso::DISCO_FUNDO);
        if (! $disco->exists($caminho)) {
            return null;
        }

        return $disco->url($caminho);
    }
}
