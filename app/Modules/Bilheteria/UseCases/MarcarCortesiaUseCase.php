<?php

namespace App\Modules\Bilheteria\UseCases;

use App\Modules\Bilheteria\Models\Ingresso;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarcarCortesiaUseCase
{
    public function execute(Ingresso $ingresso, array $dados = []): Ingresso
    {
        return DB::transaction(function () use ($ingresso, $dados) {
            $ingresso = Ingresso::query()->lockForUpdate()->findOrFail($ingresso->id);

            if ($ingresso->status !== Ingresso::STATUS_DISPONIVEL) {
                throw ValidationException::withMessages([
                    'ingresso' => ['Somente ingressos disponíveis podem ser marcados como cortesia.'],
                ]);
            }

            $ingresso->update([
                'status' => Ingresso::STATUS_VENDIDO,
                'cortesia' => true,
                'valor' => 0,
                'status_pagamento' => Ingresso::PAGAMENTO_PAGO,
                'id_conta' => null,
                'comprador_nome' => $dados['comprador_nome'] ?? $ingresso->comprador_nome,
                'comprador_documento' => $dados['comprador_documento'] ?? $ingresso->comprador_documento,
            ]);

            return $ingresso->fresh(['espetaculo']);
        });
    }
}
