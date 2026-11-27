<?php

namespace App\Modules\Financeiro\UseCases\CobrancaOnline;

use App\Modules\Core\Exceptions\BillingGatewayException;
use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaCobrancaGateway;

/**
 * Cobrança online mais relevante da conta: a em aberto ou, na falta, a mais recente.
 * Com $sync, consulta o gateway (falhas de comunicação mantêm o estado local).
 */
class ObterCobrancaOnlineContaUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
        private readonly SyncCobrancaOnlineContaUseCase $sync,
    ) {}

    public function execute(Conta $conta, bool $sync = false): ?ContaCobrancaGateway
    {
        $cobranca = ContaCobrancaGateway::query()
            ->where('id_conta', $conta->id)
            ->orderByRaw('case when status in (?, ?, ?) then 0 else 1 end', ContaCobrancaGateway::STATUS_ABERTOS)
            ->latest('id')
            ->first();

        if ($cobranca !== null && $sync && ! $cobranca->isFinal() && $this->gateway->isAvailable()) {
            try {
                $cobranca = $this->sync->execute($cobranca);
            } catch (BillingGatewayException) {
                // Mantém o estado local; o job agendado tentará novamente.
            }
        }

        return $cobranca;
    }
}
