<?php

namespace App\Modules\Core\UseCases\Assinatura;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Services\BillingGatewayClient;

/**
 * Consulta o status da subconta no Centralizador (GET /subaccounts/{uuid}).
 */
class SyncSubcontaEscolaUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
    ) {}

    public function execute(Instituicao $instituicao): Instituicao
    {
        if (blank($instituicao->gateway_subaccount_uuid)) {
            return $instituicao;
        }

        $remota = $this->gateway->getSubaccount($instituicao->gateway_subaccount_uuid);
        $status = self::mapStatus($remota['status'] ?? null);

        $instituicao->forceFill([
            'gateway_subaccount_status' => $status,
            'gateway_subaccount_last_error' => $status === Instituicao::SUBCONTA_FAILED
                ? ($remota['last_error'] ?? $instituicao->gateway_subaccount_last_error ?? 'Falha ao criar subconta.')
                : null,
        ])->save();

        return $instituicao->refresh();
    }

    public static function mapStatus(?string $status): string
    {
        return match ($status) {
            'active' => Instituicao::SUBCONTA_ACTIVE,
            'failed' => Instituicao::SUBCONTA_FAILED,
            default => Instituicao::SUBCONTA_PENDING,
        };
    }
}
