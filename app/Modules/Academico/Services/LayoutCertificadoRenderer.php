<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\TemplateCertificado;
use App\Modules\Academico\Support\CertificadoFontes;
use Illuminate\Support\Facades\Storage;

class LayoutCertificadoRenderer
{
    /**
     * @param  array{version: int, elementos: list<array<string, mixed>>}  $layout
     */
    public function paraHtml(array $layout, bool $embutirImagens = true): string
    {
        $html = '';

        foreach ($layout['elementos'] as $elemento) {
            $tipo = $elemento['tipo'] ?? '';
            $html .= match ($tipo) {
                'texto' => $this->texto($elemento),
                'imagem' => $this->imagem($elemento, $embutirImagens),
                'retangulo' => $this->retangulo($elemento),
                'linha' => $this->linha($elemento),
                default => '',
            };
        }

        return $html;
    }

    /** @param  array<string, mixed>  $el */
    private function texto(array $el): string
    {
        $estilo = $this->box($el).sprintf(
            'font-family:%s;font-size:%spx;font-weight:%s;text-align:%s;color:%s;overflow:hidden;line-height:1.3;',
            $this->escapar($this->fonteFamiliaCss($el['fonteFamilia'] ?? 'sans')),
            (int) ($el['fonteTamanho'] ?? 16),
            CertificadoFontes::pesoCss($el['fontePeso'] ?? 'normal'),
            $this->escapar($el['alinhamento'] ?? 'left'),
            $this->escapar($el['cor'] ?? '#18181b'),
        );

        $texto = (string) ($el['texto'] ?? '');

        // Se já parece HTML (legado), não escapa tudo; senão converte quebras.
        if ($this->pareceHtml($texto)) {
            $conteudo = $texto;
        } else {
            $conteudo = nl2br(e($texto));
        }

        return '<div style="'.$estilo.'">'.$conteudo.'</div>';
    }

    /** @param  array<string, mixed>  $el */
    private function imagem(array $el, bool $embutir): string
    {
        $src = (string) ($el['src'] ?? '');
        if ($src === '') {
            return '';
        }

        $uri = $embutir ? ($this->dataUri($src) ?? '') : Storage::disk(TemplateCertificado::DISCO_FUNDO)->url($src);
        if ($uri === '') {
            return '';
        }

        $estilo = $this->box($el).'overflow:hidden;';

        return '<div style="'.$estilo.'"><img src="'.e($uri).'" style="width:100%;height:100%;object-fit:contain;" alt="" /></div>';
    }

    /** @param  array<string, mixed>  $el */
    private function retangulo(array $el): string
    {
        $preenchimento = $el['preenchimento'] ?? 'transparent';
        $bordaCor = $el['bordaCor'] ?? '#312e81';
        $bordaLargura = (float) ($el['bordaLargura'] ?? 1);

        $estilo = $this->box($el).sprintf(
            'background:%s;border:%spx solid %s;box-sizing:border-box;',
            $this->escapar((string) $preenchimento),
            $bordaLargura,
            $this->escapar((string) $bordaCor),
        );

        return '<div style="'.$estilo.'"></div>';
    }

    /** @param  array<string, mixed>  $el */
    private function linha(array $el): string
    {
        $bordaCor = $el['bordaCor'] ?? '#71717a';
        $bordaLargura = max(1, (float) ($el['bordaLargura'] ?? 1));
        $x = (float) ($el['x'] ?? 0);
        $y = (float) ($el['y'] ?? 0);
        $w = (float) ($el['w'] ?? 0);

        $estilo = sprintf(
            'position:absolute;left:%s%%;top:%s%%;width:%s%%;height:0;border:0;border-top:%spx solid %s;margin:0;padding:0;',
            $this->num($x),
            $this->num($y),
            $this->num($w),
            $bordaLargura,
            $this->escapar((string) $bordaCor),
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

    private function dataUri(string $caminho): ?string
    {
        $disco = Storage::disk(TemplateCertificado::DISCO_FUNDO);
        if (! $disco->exists($caminho)) {
            return null;
        }

        $mime = $disco->mimeType($caminho) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disco->get($caminho));
    }

    private function fonteFamiliaCss(mixed $fonte): string
    {
        return CertificadoFontes::css((string) $fonte);
    }

    private function pareceHtml(string $texto): bool
    {
        return str_contains($texto, '<') && str_contains($texto, '>');
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
