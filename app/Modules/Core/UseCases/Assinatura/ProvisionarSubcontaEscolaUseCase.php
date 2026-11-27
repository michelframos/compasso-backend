<?php

namespace App\Modules\Core\UseCases\Assinatura;

use App\Modules\Core\Exceptions\BillingGatewayException;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Core\Services\BillingPayloadBuilder;
use Illuminate\Validation\ValidationException;

/**
 * Cria a subconta (Asaas) da escola no Centralizador, necessária para cobranças com split.
 */
class ProvisionarSubcontaEscolaUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
        private readonly BillingPayloadBuilder $payloads,
    ) {}

    /**
     * Idempotente: se já existe subconta pendente/ativa, apenas devolve a instituição.
     * Subcontas com status "failed" (ou "none") são (re)criadas.
     *
     * @throws ValidationException quando faltam dados da escola ou o billing está indisponível
     * @throws BillingGatewayException quando o gateway rejeita/está indisponível
     */
    public function execute(Instituicao $instituicao, ?User $user = null): Instituicao
    {
        if (
            filled($instituicao->gateway_subaccount_uuid)
            && in_array($instituicao->gateway_subaccount_status, [Instituicao::SUBCONTA_PENDING, Instituicao::SUBCONTA_ACTIVE], true)
        ) {
            return $instituicao;
        }

        if (! $this->gateway->isAvailable()) {
            throw ValidationException::withMessages([
                'billing' => ['Billing online indisponível: configure e ative o centralizador no painel Platform.'],
            ]);
        }

        $faltantes = $this->payloads->camposFaltantesSubconta($instituicao, $user);

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'subconta' => ['Complete os dados da escola para criar a subconta: '.implode(', ', $faltantes).'.'],
            ]);
        }

        try {
            $remota = $this->gateway->createSubaccount($this->payloads->subaccount($instituicao, $user));
        } catch (BillingGatewayException $e) {
            $instituicao->forceFill([
                'gateway_subaccount_status' => Instituicao::SUBCONTA_FAILED,
                'gateway_subaccount_last_error' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        $status = SyncSubcontaEscolaUseCase::mapStatus($remota['status'] ?? null);

        $instituicao->forceFill([
            'gateway_subaccount_uuid' => $remota['id'] ?? $remota['uuid'] ?? $instituicao->gateway_subaccount_uuid,
            'gateway_subaccount_status' => $status,
            'gateway_subaccount_last_error' => $status === Instituicao::SUBCONTA_FAILED
                ? ($remota['last_error'] ?? 'Falha ao criar subconta.')
                : null,
        ])->save();

        return $instituicao->refresh();
    }
}
