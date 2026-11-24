<?php

namespace App\Modules\Financeiro\UseCases\Pagamento;

use App\Modules\Core\Contracts\CriarCobrancaIngressoPort;
use App\Modules\Financeiro\Models\ContaPagamento;
use Illuminate\Support\Facades\DB;

class RemoverPagamentoUseCase
{
    public function __construct(private readonly CriarCobrancaIngressoPort $cobrancaIngresso) {}

    public function execute(ContaPagamento $pagamento): void
    {
        DB::transaction(function () use ($pagamento) {
            $conta = $pagamento->conta;
            $pagamento->delete();
            $conta->recalcularStatus();
            $this->cobrancaIngresso->sincronizarStatusConta($conta->fresh());
        });
    }
}
