<?php

namespace App\Modules\Bilheteria\UseCases;

use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Bilheteria\Models\TemplateIngresso;
use App\Modules\Bilheteria\Services\LayoutIngressoRenderer;
use App\Modules\Bilheteria\Services\QrCodeIngressoService;
use App\Modules\Bilheteria\Support\LayoutIngresso;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Espetaculos\Models\Espetaculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GerarIngressosUseCase
{
    public function __construct(
        private readonly LayoutIngressoRenderer $renderer,
        private readonly QrCodeIngressoService $qrCode,
    ) {}

    /**
     * @return list<Ingresso>
     */
    public function execute(Espetaculo $espetaculo, int $quantidade, ?float $valor = null, ?int $idTemplate = null): array
    {
        if ($quantidade < 1 || $quantidade > 500) {
            throw ValidationException::withMessages([
                'quantidade' => ['Informe entre 1 e 500 ingressos.'],
            ]);
        }

        $valorUnitario = $valor ?? $espetaculo->valor_ingresso_padrao ?? 0;
        $templateId = $idTemplate ?? $espetaculo->id_template_ingresso;
        $template = $templateId
            ? TemplateIngresso::query()->find($templateId)
            : null;

        return DB::transaction(function () use ($espetaculo, $quantidade, $valorUnitario, $template) {
            $criados = [];
            $proximo = $this->proximoNumeroSequencial();

            for ($i = 0; $i < $quantidade; $i++) {
                $numero = (string) ($proximo + $i);
                $codigoQr = Str::lower(Str::ulid()->toString());

                $placeholders = $this->placeholders($espetaculo, $numero, $valorUnitario, $codigoQr);
                $conteudoGerado = null;
                $fundoPath = null;

                if ($template) {
                    $layout = LayoutIngresso::parse($template->conteudo);
                    $conteudoGerado = $this->renderer->paraHtml($layout, $placeholders);
                    $fundoPath = $template->fundo_path;
                }

                $criados[] = Ingresso::create([
                    'id_instituicao' => InstituicaoContext::id(),
                    'id_espetaculo' => $espetaculo->id,
                    'id_template_ingresso' => $template?->id,
                    'numero' => $numero,
                    'codigo_qr' => $codigoQr,
                    'valor' => $valorUnitario,
                    'status' => Ingresso::STATUS_DISPONIVEL,
                    'cortesia' => false,
                    'status_pagamento' => null,
                    'conteudo_gerado' => $conteudoGerado,
                    'fundo_path' => $fundoPath,
                ]);
            }

            return $criados;
        });
    }

    private function proximoNumeroSequencial(): int
    {
        $max = Ingresso::query()
            ->withTrashed()
            ->pluck('numero')
            ->filter(fn ($n) => ctype_digit((string) $n))
            ->map(fn ($n) => (int) $n)
            ->max();

        return ($max ?: 0) + 1;
    }

    /** @return array<string, string> */
    private function placeholders(Espetaculo $espetaculo, string $numero, float $valor, string $codigoQr): array
    {
        $hora = $espetaculo->hora_evento
            ? substr((string) $espetaculo->hora_evento, 0, 5)
            : '';

        return [
            '{{numero}}' => $numero,
            '{{espetaculo}}' => (string) $espetaculo->titulo,
            '{{data}}' => $espetaculo->data_evento?->format('d/m/Y') ?? '',
            '{{hora}}' => $hora,
            '{{local}}' => (string) ($espetaculo->local ?? ''),
            '{{valor}}' => 'R$ '.number_format($valor, 2, ',', '.'),
            '{{qr_ingresso}}' => $this->qrCode->dataUri($codigoQr),
            'qr_ingresso' => $codigoQr,
        ];
    }
}
