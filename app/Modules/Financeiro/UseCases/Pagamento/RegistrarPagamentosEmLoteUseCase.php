<?php

namespace App\Modules\Financeiro\UseCases\Pagamento;

use App\Modules\Financeiro\Models\Conta;
use Illuminate\Support\Facades\DB;

class RegistrarPagamentosEmLoteUseCase
{
    /**
     * Quita o saldo devedor de cada conta informada. Contas inexistentes, sem acesso,
     * já pagas ou canceladas são ignoradas.
     *
     * @param  (callable(Conta): bool)|null  $podeAcessar
     * @return list<int> ids das contas quitadas
     */
    public function execute(array $dados, ?callable $podeAcessar = null): array
    {
        return DB::transaction(function () use ($dados, $podeAcessar) {
            $contasPagas = [];

            foreach ($dados['conta_ids'] as $contaId) {
                $conta = Conta::lockForUpdate()->find($contaId);

                if (! $conta || ($podeAcessar && ! $podeAcessar($conta))) {
                    continue;
                }

                if (in_array($conta->status, ['pago', 'cancelado'], true)) {
                    continue;
                }

                $saldoDevedor = $conta->saldoDevedor();

                if ($saldoDevedor > 0) {
                    $conta->pagamentos()->create([
                        'valor_pago' => $saldoDevedor,
                        'data_pagamento' => $dados['data_pagamento'],
                        'forma_pagamento' => $dados['forma_pagamento'],
                        'observacoes' => $dados['observacoes'] ?? null,
                    ]);
                }

                $conta->update(['status' => 'pago']);

                if ($saldoDevedor > 0) {
                    $contasPagas[] = $conta->id;
                }
            }

            return $contasPagas;
        });
    }
}
