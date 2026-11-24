<?php

namespace App\Modules\Bilheteria\UseCases;

use App\Modules\Bilheteria\Models\Ingresso;
use Carbon\Carbon;

class ValidarIngressoUseCase
{
    /**
     * @return array{ok: bool, motivo?: string, ingresso?: Ingresso}
     */
    public function execute(?string $numero = null, ?string $codigoQr = null): array
    {
        $ingresso = $this->localizar($numero, $codigoQr);

        if (! $ingresso) {
            return ['ok' => false, 'motivo' => 'Ingresso não encontrado.'];
        }

        if ($ingresso->status === Ingresso::STATUS_UTILIZADO) {
            return [
                'ok' => false,
                'motivo' => 'O ingresso já foi utilizado.',
                'ingresso' => $ingresso,
            ];
        }

        if ($ingresso->status === Ingresso::STATUS_DISPONIVEL) {
            return [
                'ok' => false,
                'motivo' => 'O ingresso ainda não foi vendido.',
                'ingresso' => $ingresso,
            ];
        }

        if ($ingresso->status_pagamento === Ingresso::PAGAMENTO_CANCELADO) {
            return [
                'ok' => false,
                'motivo' => 'O pagamento deste ingresso foi cancelado.',
                'ingresso' => $ingresso,
            ];
        }

        if ($ingresso->status_pagamento !== Ingresso::PAGAMENTO_PAGO && ! $ingresso->cortesia) {
            return [
                'ok' => false,
                'motivo' => 'O ingresso ainda não está pago.',
                'ingresso' => $ingresso,
            ];
        }

        $espetaculo = $ingresso->espetaculo;
        if (! $espetaculo) {
            return ['ok' => false, 'motivo' => 'Espetáculo do ingresso não encontrado.', 'ingresso' => $ingresso];
        }

        [$inicio, $fim] = $espetaculo->janelaValidadeIngresso();
        $agora = Carbon::now();

        if ($agora->lt($inicio) || $agora->gt($fim)) {
            return [
                'ok' => false,
                'motivo' => 'O ingresso é inválido (fora do período de uso).',
                'ingresso' => $ingresso,
            ];
        }

        return ['ok' => true, 'ingresso' => $ingresso];
    }

    private function localizar(?string $numero, ?string $codigoQr): ?Ingresso
    {
        $query = Ingresso::query()->with('espetaculo');

        if ($codigoQr) {
            return $query->where('codigo_qr', $codigoQr)->first();
        }

        if ($numero) {
            return $query->where('numero', $numero)->first();
        }

        return null;
    }
}
