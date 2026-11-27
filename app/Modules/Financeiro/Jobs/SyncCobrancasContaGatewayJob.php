<?php

namespace App\Modules\Financeiro\Jobs;

use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Financeiro\Models\ContaCobrancaGateway;
use App\Modules\Financeiro\UseCases\CobrancaOnline\SyncCobrancaOnlineContaUseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polling do Centralizador (sem webhook outbound no MVP) das cobranças online de mensalidades
 * ainda não finalizadas, de todas as escolas.
 */
class SyncCobrancasContaGatewayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BillingGatewayClient $gateway, SyncCobrancaOnlineContaUseCase $sync): void
    {
        if (! $gateway->isAvailable()) {
            return;
        }

        ContaCobrancaGateway::query()
            ->withoutInstituicaoScope()
            ->naoFinais()
            ->whereNotNull('gateway_payment_uuid')
            ->orderBy('id')
            ->each(function (ContaCobrancaGateway $cobranca) use ($sync): void {
                try {
                    $sync->execute($cobranca);
                } catch (Throwable $e) {
                    Log::warning('Falha ao sincronizar cobrança online de conta com o gateway.', [
                        'id_cobranca_gateway' => $cobranca->id,
                        'erro' => $e->getMessage(),
                    ]);
                }
            });
    }
}
