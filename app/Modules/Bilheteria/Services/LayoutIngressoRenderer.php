<?php

namespace App\Modules\Bilheteria\Services;

use App\Modules\Bilheteria\Models\TemplateIngresso;
use Illuminate\Support\Facades\Storage;

class LayoutIngressoRenderer
{
    public function __construct(private readonly QrCodeIngressoService $qrCode) {}

    /**
     * @param  array{version: int, elementos: list<array<string, mixed>>}  $layout
     * @param  array<string, string>  $placeholders
     */
    public function paraHtml(array $layout, array $placeholders = [], bool $embutirImagens = true): string
    {
        $html = '';

        foreach ($layout['elementos'] as $elemento) {
            $tipo = $elemento['tipo'] ?? '';
            $html .= match ($tipo) {
                'texto' => $this->texto($elemento, $placeholders),
                'imagem' => $this->imagem($elemento, $embutirImagens),
                'qrcode' => $this->qrcode($elemento, $placeholders),
                'retangulo' => $this->retangulo($elemento),
                'linha' => $this->linha($elemento),
                default => '',
            };
        }

        return $html;
    }

    /** @param  array<string, mixed>  $el */
    private function texto(array $el, array $placeholders): string
    {
        $texto = $this->substituir((string) ($el['texto'] ?? ''), $placeholders);
        $estilo = $this->box($el).sprintf(
            'font-family:DejaVu Sans,sans-serif;font-size:%spx;font-weight:%s;text-align:%s;color:%s;overflow:hidden;line-height:1.3;',
            (int) ($el['fonteTamanho'] ?? 14),
            $this->escapar((string) ($el['fontePeso'] ?? 'normal')),
            $this->escapar((string) ($el['alinhamento'] ?? 'left')),
            $this->escapar((string) ($el['cor'] ?? '#18181b')),
        );

        return '<div style="'.$estilo.'">'.nl2br(e($texto)).'</div>';
    }

    /** @param  array<string, mixed>  $el */
    private function qrcode(array $el, array $placeholders): string
    {
        $payload = $placeholders['{{qr_ingresso}}'] ?? $placeholders['qr_ingresso'] ?? '';
        if ($payload === '') {
            return '';
        }

        $uri = str_starts_with($payload, 'data:')
            ? $payload
            : $this->qrCode->dataUri($payload);

        $estilo = $this->box($el).'overflow:hidden;';

        return '<div style="'.$estilo.'"><img src="'.e($uri).'" style="width:100%;height:100%;object-fit:contain;" alt="QR" /></div>';
    }

    /** @param  array<string, mixed>  $el */
    private function imagem(array $el, bool $embutir): string
    {
        $src = (string) ($el['src'] ?? '');
        if ($src === '') {
            return '';
        }

        $uri = $embutir ? ($this->dataUri($src) ?? '') : Storage::disk(TemplateIngresso::DISCO_FUNDO)->url($src);
        if ($uri === '') {
            return '';
        }

        $estilo = $this->box($el).'overflow:hidden;';

        return '<div style="'.$estilo.'"><img src="'.e($uri).'" style="width:100%;height:100%;object-fit:contain;" alt="" /></div>';
    }

    /** @param  array<string, mixed>  $el */
    private function retangulo(array $el): string
    {
        $estilo = $this->box($el).sprintf(
            'background:%s;border:%spx solid %s;box-sizing:border-box;',
            $this->escapar((string) ($el['preenchimento'] ?? 'transparent')),
            (float) ($el['bordaLargura'] ?? 1),
            $this->escapar((string) ($el['bordaCor'] ?? '#312e81')),
        );

        return '<div style="'.$estilo.'"></div>';
    }

    /** @param  array<string, mixed>  $el */
    private function linha(array $el): string
    {
        $estilo = sprintf(
            'position:absolute;left:%s%%;top:%s%%;width:%s%%;height:0;border:0;border-top:%spx solid %s;margin:0;padding:0;',
            $this->num((float) ($el['x'] ?? 0)),
            $this->num((float) ($el['y'] ?? 0)),
            $this->num((float) ($el['w'] ?? 0)),
            max(1, (float) ($el['bordaLargura'] ?? 1)),
            $this->escapar((string) ($el['bordaCor'] ?? '#71717a')),
        );

        return '<hr style="'.$estilo.'" />';
    }

    /** @param  array<string, mixed>  $el */
    private function box(array $el): string
    {
        return sprintf(
            'position:absolute;left:%s%%;top:%s%%;width:%s%%;height:%s%%;',
            $this->num((float) ($el['x'] ?? 0)),
            $this->num((float) ($el['y'] ?? 0)),
            $this->num((float) ($el['w'] ?? 10)),
            $this->num((float) ($el['h'] ?? 10)),
        );
    }

    /** @param  array<string, string>  $placeholders */
    private function substituir(string $texto, array $placeholders): string
    {
        foreach ($placeholders as $chave => $valor) {
            $token = str_starts_with($chave, '{{') ? $chave : '{{'.$chave.'}}';
            $texto = str_replace($token, $valor, $texto);
        }

        return $texto;
    }

    private function dataUri(string $caminho): ?string
    {
        $disco = Storage::disk(TemplateIngresso::DISCO_FUNDO);
        if (! $disco->exists($caminho)) {
            return null;
        }

        $mime = $disco->mimeType($caminho) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disco->get($caminho));
    }

    private function num(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 4, '.', ''), '0'), '.');
    }

    private function escapar(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
