<?php

namespace App\Modules\Financeiro\UseCases\CobrancaOnline;

use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaCobrancaGateway;
use Illuminate\Validation\ValidationException;

/**
 * Cancela a cobrança online em aberto da conta (gateway + status local). Cobranças pagas não são canceladas.
 */
class CancelarCobrancaOnlineContaUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
        private readonly SyncCobrancaOnlineContaUseCase $sync,
    ) {}

    public function execute(Conta $conta): ContaCobrancaGateway
    {
        if (! $this->gateway->isAvailable()) {
            throw ValidationException::withMessages([
                'billing' => ['Cobrança online indisponível no momento.'],
            ]);
        }

        $cobranca = ContaCobrancaGateway::query()
            ->where('id_conta', $conta->id)
            ->naoFinais()
            ->latest('id')
            ->first();

        if ($cobranca === null) {
            throw ValidationException::withMessages([
                'cobranca' => ['Nenhuma cobrança online em aberto para cancelar.'],
            ]);
        }

        if (filled($cobranca->gateway_payment_uuid)) {
            // Evita cancelar algo que já foi pago e ainda não sincronizado.
            $cobranca = $this->sync->execute($cobranca);

            if ($cobranca->status === ContaCobrancaGateway::STATUS_PAID) {
                throw ValidationException::withMessages([
                    'cobranca' => ['Esta cobrança já foi paga e não pode ser cancelada.'],
                ]);
            }

            if ($cobranca->isFinal()) {
                return $cobranca;
            }

            $this->gateway->cancelPayment($cobranca->gateway_payment_uuid);
        }

        $cobranca->forceFill([
            'status' => ContaCobrancaGateway::STATUS_CANCELED,
            'last_error' => null,
        ])->save();

        return $cobranca->refresh();
    }
}
