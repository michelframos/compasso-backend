<?php

namespace App\Modules\Financeiro\Adapters;

use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Core\Contracts\CriarCobrancaIngressoPort;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;

class CriarCobrancaIngressoAdapter implements CriarCobrancaIngressoPort
{
    public function criar(array $params): Conta
    {
        $categoria = CategoriaConta::firstOrCreate(
            ['nome' => 'Venda de Ingressos'],
            [
                'tipo' => 'receita',
                'descricao' => 'Receitas de venda de ingressos de espetáculos',
            ]
        );

        return Conta::create([
            'id_instituicao' => InstituicaoContext::id(),
            'id_categoria' => $categoria->id,
            'descricao' => $params['descricao'],
            'valor' => $params['valor'],
            'data_vencimento' => $params['data_vencimento'],
            'status' => 'pendente',
            'tipo' => 'receita',
            'observacoes' => $params['observacoes'] ?? ('Ingresso #'.$params['ingresso_id']),
        ]);
    }

    public function cancelar(int $idConta, string $observacao = ''): bool
    {
        $conta = Conta::query()->find($idConta);
        if (! $conta || $conta->status === 'cancelado') {
            return false;
        }

        if ($conta->pagamentos()->exists()) {
            return false;
        }

        $conta->update([
            'status' => 'cancelado',
            'observacoes' => trim(($conta->observacoes ? $conta->observacoes."\n" : '').$observacao),
        ]);

        $this->sincronizarStatusConta($conta->fresh());

        return true;
    }

    public function sincronizarStatusConta(Conta $conta): void
    {
        $ingresso = Ingresso::query()->where('id_conta', $conta->id)->first();
        if (! $ingresso) {
            return;
        }

        $statusPagamento = match ($conta->status) {
            'pago' => Ingresso::PAGAMENTO_PAGO,
            'cancelado' => Ingresso::PAGAMENTO_CANCELADO,
            default => Ingresso::PAGAMENTO_AGUARDANDO,
        };

        $ingresso->update(['status_pagamento' => $statusPagamento]);
    }
}
