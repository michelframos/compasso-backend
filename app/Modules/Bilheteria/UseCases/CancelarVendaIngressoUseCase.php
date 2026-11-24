<?php

namespace App\Modules\Bilheteria\UseCases;

use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Core\Contracts\CriarCobrancaIngressoPort;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelarVendaIngressoUseCase
{
    public function __construct(private readonly CriarCobrancaIngressoPort $cobrancaIngresso) {}

    public function execute(Ingresso $ingresso): Ingresso
    {
        return DB::transaction(function () use ($ingresso) {
            $ingresso = Ingresso::query()->lockForUpdate()->findOrFail($ingresso->id);

            if ($ingresso->status === Ingresso::STATUS_UTILIZADO) {
                throw ValidationException::withMessages([
                    'ingresso' => ['Não é possível cancelar um ingresso já utilizado.'],
                ]);
            }

            if ($ingresso->status !== Ingresso::STATUS_VENDIDO) {
                throw ValidationException::withMessages([
                    'ingresso' => ['Somente ingressos vendidos podem ser cancelados.'],
                ]);
            }

            if ($ingresso->id_conta) {
                $ok = $this->cobrancaIngresso->cancelar(
                    (int) $ingresso->id_conta,
                    'Venda de ingresso cancelada.'
                );

                if (! $ok) {
                    throw ValidationException::withMessages([
                        'ingresso' => ['Não foi possível cancelar: a conta financeira já possui pagamento.'],
                    ]);
                }
            }

            $ingresso->update([
                'status' => Ingresso::STATUS_DISPONIVEL,
                'cortesia' => false,
                'status_pagamento' => null,
                'id_conta' => null,
                'id_cupom' => null,
                'comprador_nome' => null,
                'comprador_documento' => null,
            ]);

            return $ingresso->fresh(['espetaculo']);
        });
    }
}
