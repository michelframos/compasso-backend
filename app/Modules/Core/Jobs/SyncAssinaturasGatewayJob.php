<?php

namespace App\Modules\Core\Jobs;

use App\Modules\Core\Models\AssinaturaCobranca;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Core\UseCases\Assinatura\SyncAssinaturaCobrancaUseCase;
use App\Modules\Core\UseCases\Assinatura\SyncSubcontaEscolaUseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polling do Centralizador (sem webhook outbound no MVP):
 * - assinaturas não finais (pending/awaiting_payment/past_due a cada execução; active a cada hora);
 * - subcontas de escolas com status "pending".
 */
class SyncAssinaturasGatewayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Intervalo mínimo (minutos) entre syncs de assinaturas já vigentes (active). */
    private const REVALIDAR_ATIVAS_MINUTOS = 60;

    public function handle(
        BillingGatewayClient $gateway,
        SyncAssinaturaCobrancaUseCase $syncAssinatura,
        SyncSubcontaEscolaUseCase $syncSubconta,
    ): void {
        if (! $gateway->isAvailable()) {
            return;
        }

        AssinaturaCobranca::query()
            ->naoFinais()
            ->whereNotNull('gateway_subscription_uuid')
            ->where(function ($query): void {
                $query->where('status', '!=', AssinaturaCobranca::STATUS_ACTIVE)
                    ->orWhere('updated_at', '<=', now()->subMinutes(self::REVALIDAR_ATIVAS_MINUTOS));
            })
            ->orderBy('id')
            ->each(function (AssinaturaCobranca $assinatura) use ($syncAssinatura): void {
                try {
                    $syncAssinatura->execute($assinatura);
                } catch (Throwable $e) {
                    Log::warning('Falha ao sincronizar assinatura com o gateway.', [
                        'id_assinatura_cobranca' => $assinatura->id,
                        'erro' => $e->getMessage(),
                    ]);
                }
            });

        Instituicao::query()
            ->where('gateway_subaccount_status', Instituicao::SUBCONTA_PENDING)
            ->whereNotNull('gateway_subaccount_uuid')
            ->orderBy('id')
            ->each(function (Instituicao $instituicao) use ($syncSubconta): void {
                try {
                    $syncSubconta->execute($instituicao);
                } catch (Throwable $e) {
                    Log::warning('Falha ao sincronizar subconta com o gateway.', [
                        'id_instituicao' => $instituicao->id,
                        'erro' => $e->getMessage(),
                    ]);
                }
            });
    }
}
