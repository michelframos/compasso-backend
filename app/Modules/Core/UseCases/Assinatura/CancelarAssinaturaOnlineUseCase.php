<?php

namespace App\Modules\Core\UseCases\Assinatura;

use App\Modules\Core\Models\AssinaturaCobranca;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Services\BillingGatewayClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancela a assinatura online vigente/aberta da escola (gateway + status local).
 * A subconta não é removida.
 */
class CancelarAssinaturaOnlineUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
    ) {}

    public function execute(Instituicao $instituicao): AssinaturaCobranca
    {
        $assinatura = AssinaturaCobranca::query()
            ->where('id_instituicao', $instituicao->id)
            ->naoFinais()
            ->latest('id')
            ->first();

        if ($assinatura === null) {
            throw ValidationException::withMessages([
                'assinatura' => ['Nenhuma assinatura online ativa para cancelar.'],
            ]);
        }

        if (filled($assinatura->gateway_subscription_uuid)) {
            $this->gateway->cancelSubscription($assinatura->gateway_subscription_uuid);
        }

        $estavaVigente = $assinatura->isVigente();

        return DB::transaction(function () use ($assinatura, $instituicao, $estavaVigente): AssinaturaCobranca {
            $assinatura->forceFill([
                'status' => AssinaturaCobranca::STATUS_CANCELED,
                'invoice_url' => null,
            ])->save();

            // Só rebaixa a escola se esta assinatura online já estava vigente (pago).
            if ($estavaVigente) {
                $instituicao->forceFill(['assinatura_status' => Instituicao::ASSINATURA_CANCELED])->save();
            }

            return $assinatura->refresh()->load('plano');
        });
    }
}
