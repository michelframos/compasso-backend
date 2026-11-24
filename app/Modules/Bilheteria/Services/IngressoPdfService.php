<?php

namespace App\Modules\Bilheteria\Services;

use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Bilheteria\Models\TemplateIngresso;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class IngressoPdfService
{
    public function __construct(private readonly QrCodeIngressoService $qrCode) {}

    public function download(Ingresso $ingresso): Response
    {
        $ingresso->loadMissing(['espetaculo', 'template', 'instituicao']);

        if (filled($ingresso->conteudo_gerado)) {
            return $this->downloadComTemplate($ingresso);
        }

        return $this->downloadLegado($ingresso);
    }

    private function downloadComTemplate(Ingresso $ingresso): Response
    {
        $mm = $ingresso->template?->dimensoesEmMm() ?? [
            'largura' => 150.0,
            'altura' => 70.0,
        ];

        $pdf = Pdf::loadView('pdf.ingresso-template', [
            'conteudo' => $this->normalizarConteudoHtml((string) $ingresso->conteudo_gerado),
            'fundo_data_uri' => $this->fundoDataUri($ingresso->fundo_path),
            'largura_mm' => $mm['largura'],
            'altura_mm' => $mm['altura'],
        ])->setPaper([0, 0, $this->mmParaPt((float) $mm['largura']), $this->mmParaPt((float) $mm['altura'])]);

        return $pdf->download('ingresso_'.$ingresso->numero.'.pdf');
    }

    /**
     * Corrige HTML já persistido (overflow/clipping e fundos opacos).
     */
    public function normalizarConteudoHtml(string $conteudo): string
    {
        // Só afeta blocos de texto (têm line-height); QR/imagem mantêm overflow:hidden.
        $conteudo = str_replace('overflow:hidden;line-height:1.3;', 'overflow:visible;line-height:1.15;', $conteudo);
        $conteudo = str_replace('overflow:hidden;line-height:1.15;', 'overflow:visible;line-height:1.15;', $conteudo);

        // Retângulos "transparentes" no DomPDF viram branco e cobrem o texto.
        $conteudo = str_replace('background:transparent;', 'background:none;', $conteudo);

        return $conteudo;
    }

    private function downloadLegado(Ingresso $ingresso): Response
    {
        $espetaculo = $ingresso->espetaculo;
        $hora = $espetaculo?->hora_evento
            ? substr((string) $espetaculo->hora_evento, 0, 5)
            : null;

        $pdf = Pdf::loadView('pdf.ingresso', [
            'escola' => $ingresso->instituicao?->nome_fantasia ?? 'Escola',
            'numero' => $ingresso->numero,
            'codigo_qr' => $ingresso->codigo_qr,
            'qr_data_uri' => $this->qrCode->dataUri($ingresso->codigo_qr),
            'espetaculo' => $espetaculo?->titulo,
            'data' => $espetaculo?->data_evento?->format('d/m/Y'),
            'hora' => $hora,
            'local' => $espetaculo?->local,
            'comprador' => $ingresso->comprador_nome,
            'status' => $ingresso->rotuloStatus(),
            'valor' => number_format((float) $ingresso->valor, 2, ',', '.'),
        ])->setPaper([0, 0, $this->mmParaPt(150), $this->mmParaPt(70)]);

        return $pdf->download('ingresso_'.$ingresso->numero.'.pdf');
    }

    private function fundoDataUri(?string $caminho): ?string
    {
        if (! $caminho) {
            return null;
        }

        $disco = Storage::disk(TemplateIngresso::DISCO_FUNDO);
        if (! $disco->exists($caminho)) {
            return null;
        }

        $conteudo = $disco->get($caminho);
        $mime = $disco->mimeType($caminho) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($conteudo);
    }

    private function mmParaPt(float $mm): float
    {
        return round($mm * 72 / 25.4, 2);
    }
}
