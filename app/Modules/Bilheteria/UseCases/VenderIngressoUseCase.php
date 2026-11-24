<?php

namespace App\Modules\Bilheteria\UseCases;

use App\Modules\Bilheteria\Models\CupomIngresso;
use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Core\Contracts\CriarCobrancaIngressoPort;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenderIngressoUseCase
{
    public function __construct(private readonly CriarCobrancaIngressoPort $cobrancaIngresso) {}

    public function execute(Ingresso $ingresso, array $dados): Ingresso
    {
        return DB::transaction(function () use ($ingresso, $dados) {
            $ingresso = Ingresso::query()->lockForUpdate()->findOrFail($ingresso->id);

            if ($ingresso->status !== Ingresso::STATUS_DISPONIVEL) {
                throw ValidationException::withMessages([
                    'ingresso' => ['Somente ingressos disponíveis podem ser vendidos.'],
                ]);
            }

            $valor = (float) $ingresso->valor;
            $cupom = null;

            if (! empty($dados['cupom'])) {
                $cupom = CupomIngresso::query()
                    ->where('codigo', strtoupper(trim((string) $dados['cupom'])))
                    ->first();

                if (! $cupom || ! $cupom->estaValidoPara((int) $ingresso->id_espetaculo)) {
                    throw ValidationException::withMessages([
                        'cupom' => ['Cupom inválido ou expirado.'],
                    ]);
                }

                $valor = $cupom->aplicarDesconto($valor);
            }

            $espetaculo = $ingresso->espetaculo;
            $descricao = sprintf(
                'Ingresso %s — %s',
                $ingresso->numero,
                $espetaculo?->titulo ?? 'Espetáculo'
            );

            $conta = $this->cobrancaIngresso->criar([
                'ingresso_id' => $ingresso->id,
                'descricao' => $descricao,
                'valor' => $valor,
                'data_vencimento' => $dados['data_vencimento'] ?? now()->toDateString(),
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            if ($cupom) {
                $cupom->increment('usos');
            }

            $ingresso->update([
                'status' => Ingresso::STATUS_VENDIDO,
                'cortesia' => false,
                'valor' => $valor,
                'status_pagamento' => Ingresso::PAGAMENTO_AGUARDANDO,
                'id_conta' => $conta->id,
                'id_cupom' => $cupom?->id,
                'comprador_nome' => $dados['comprador_nome'] ?? null,
                'comprador_documento' => $dados['comprador_documento'] ?? null,
            ]);

            return $ingresso->fresh(['espetaculo', 'conta', 'cupom']);
        });
    }
}
