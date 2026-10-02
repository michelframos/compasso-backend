<?php

namespace App\Modules\Financeiro\UseCases\Pagamento;

use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaPagamento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarPagamentoUseCase
{
    public function execute(Conta $conta, array $dados): ContaPagamento
    {
        return DB::transaction(function () use ($conta, $dados) {
            $conta = Conta::query()->lockForUpdate()->findOrFail($conta->id);
            $saldoDevedor = $conta->saldoDevedor();

            if ($dados['valor_pago'] > $saldoDevedor) {
                throw ValidationException::withMessages([
                    'valor_pago' => ['O valor do pagamento (R$ '.$this->formatar($dados['valor_pago']).') não pode ser maior que o saldo devedor da conta (R$ '.$this->formatar($saldoDevedor).').'],
                ]);
            }

            $pagamento = $conta->pagamentos()->create($dados);
            $conta->recalcularStatus();

            return $pagamento;
        });
    }

    private function formatar(float|int|string $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }
}
