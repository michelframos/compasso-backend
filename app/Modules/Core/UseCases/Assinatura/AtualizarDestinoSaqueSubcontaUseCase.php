<?php

namespace App\Modules\Core\UseCases\Assinatura;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Services\BillingGatewayClient;
use Illuminate\Validation\ValidationException;

/**
 * Cadastra o destino de saque (chave PIX) da subconta da escola no Centralizador.
 */
class AtualizarDestinoSaqueSubcontaUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
    ) {}

    /**
     * @param  array{pix_key: string, pix_key_type: string}  $data
     * @return array<string, mixed> subconta devolvida pelo gateway
     */
    public function execute(Instituicao $instituicao, array $data): array
    {
        if (! $this->gateway->isAvailable()) {
            throw ValidationException::withMessages([
                'billing' => ['Billing online indisponível.'],
            ]);
        }

        if (blank($instituicao->gateway_subaccount_uuid) || ! $instituicao->subcontaAtiva()) {
            throw ValidationException::withMessages([
                'subconta' => ['A subconta precisa estar ativa para cadastrar o destino de saque.'],
            ]);
        }

        return $this->gateway->updatePayoutDestination($instituicao->gateway_subaccount_uuid, [
            'payout_type' => 'pix',
            'pix_key' => $data['pix_key'],
            'pix_key_type' => $data['pix_key_type'],
        ]);
    }
}
