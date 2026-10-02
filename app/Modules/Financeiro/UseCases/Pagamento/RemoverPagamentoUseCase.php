<?php

namespace App\Modules\Financeiro\UseCases\Pagamento;

use App\Modules\Financeiro\Models\ContaPagamento;
use Illuminate\Support\Facades\DB;

class RemoverPagamentoUseCase
{
    public function execute(ContaPagamento $pagamento): void
    {
        DB::transaction(function () use ($pagamento) {
            $conta = $pagamento->conta;
            $pagamento->delete();
            $conta->recalcularStatus();
        });
    }
}
