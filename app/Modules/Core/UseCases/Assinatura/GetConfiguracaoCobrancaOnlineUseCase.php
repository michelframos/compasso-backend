<?php

namespace App\Modules\Core\UseCases\Assinatura;

use App\Modules\Core\Exceptions\BillingGatewayException;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Services\BillingGatewayClient;

class GetConfiguracaoCobrancaOnlineUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
        private readonly SyncSubcontaEscolaUseCase $syncSubconta,
    ) {}

    /**
     * @return array{meios: list<string>, meios_disponiveis: list<string>, billing_disponivel: bool, subconta_status: string, subconta_ativa: bool, subconta_last_error: string|null}
     */
    public function execute(Instituicao $instituicao): array
    {
        if (
            $this->gateway->isAvailable()
            && $instituicao->gateway_subaccount_status === Instituicao::SUBCONTA_PENDING
            && filled($instituicao->gateway_subaccount_uuid)
        ) {
            try {
                $instituicao = $this->syncSubconta->execute($instituicao);
            } catch (BillingGatewayException) {
                // Mantém o status local se o gateway estiver momentaneamente indisponível.
            }
        }

        return [
            'meios' => $instituicao->meiosCobrancaOnline(),
            'meios_disponiveis' => Instituicao::MEIOS_COBRANCA_DISPONIVEIS,
            'billing_disponivel' => $this->gateway->isAvailable(),
            'subconta_status' => $instituicao->gateway_subaccount_status ?? Instituicao::SUBCONTA_NONE,
            'subconta_ativa' => $instituicao->subcontaAtiva(),
            'subconta_last_error' => $instituicao->gateway_subaccount_last_error,
        ];
    }
}
