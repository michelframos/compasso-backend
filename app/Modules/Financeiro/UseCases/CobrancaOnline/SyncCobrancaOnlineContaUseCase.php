<?php

namespace App\Modules\Financeiro\UseCases\CobrancaOnline;

use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaCobrancaGateway;
use App\Modules\Financeiro\UseCases\Pagamento\RegistrarPagamentoUseCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sincroniza a cobrança local com o Centralizador (GET /payments/{uuid}?sync=1).
 *
 * Mapeamento do status do gateway:
 * - pending / awaiting_payment / overdue / failed → mesmo status local
 * - canceled / refunded / deleted / expired       → canceled
 * - paid                                          → paid (registra ContaPagamento uma única vez)
 * - desconhecido                                  → mantém o status atual
 */
class SyncCobrancaOnlineContaUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
        private readonly RegistrarPagamentoUseCase $registrarPagamento,
    ) {}

    public function execute(ContaCobrancaGateway $cobranca): ContaCobrancaGateway
    {
        if ($cobranca->isFinal() || blank($cobranca->gateway_payment_uuid)) {
            return $cobranca;
        }

        $remota = $this->gateway->getPayment($cobranca->gateway_payment_uuid, sync: true);

        return $this->aplicarRemota($cobranca, $remota);
    }

    /**
     * Aplica o payload do gateway (POST/GET /payments) na cobrança local.
     *
     * @param  array<string, mixed>  $remota
     */
    public function aplicarRemota(ContaCobrancaGateway $cobranca, array $remota): ContaCobrancaGateway
    {
        $status = $this->mapearStatus($remota['status'] ?? null) ?? $cobranca->status;
        $pago = $status === ContaCobrancaGateway::STATUS_PAID;

        $atributos = array_filter([
            'gateway_payment_uuid' => $cobranca->gateway_payment_uuid ?: ($remota['id'] ?? $remota['uuid'] ?? null),
            'invoice_url' => $remota['invoice_url'] ?? $remota['bank_slip_url'] ?? null,
            'pix_copy_paste' => $remota['pix_copy_paste'] ?? null,
            'pix_qr_code' => $remota['pix_qr_code'] ?? null,
        ], fn ($valor) => filled($valor));

        // "paid" só é gravado junto com o ContaPagamento (registrarPagamentoRecebido).
        if (! $pago) {
            $atributos['status'] = $status;
            $atributos['last_error'] = $status === ContaCobrancaGateway::STATUS_FAILED
                ? ($remota['last_error'] ?? 'Falha ao criar a cobrança no gateway.')
                : null;
        }

        $cobranca->forceFill($atributos)->save();

        if ($pago) {
            return $this->registrarPagamentoRecebido($cobranca);
        }

        if ($cobranca->isAberta()) {
            $this->cancelarSeContaQuitada($cobranca);
        }

        return $cobranca->refresh();
    }

    private function registrarPagamentoRecebido(ContaCobrancaGateway $cobranca): ContaCobrancaGateway
    {
        return InstituicaoContext::runWith((int) $cobranca->id_instituicao, null, function () use ($cobranca): ContaCobrancaGateway {
            return DB::transaction(function () use ($cobranca): ContaCobrancaGateway {
                $atual = ContaCobrancaGateway::query()->lockForUpdate()->findOrFail($cobranca->id);

                if ($atual->id_conta_pagamento === null) {
                    $atual->id_conta_pagamento = $this->registrarPagamentoDaConta($atual);
                }

                $atual->forceFill([
                    'status' => ContaCobrancaGateway::STATUS_PAID,
                    'last_error' => null,
                ])->save();

                return $atual->refresh();
            });
        });
    }

    private function registrarPagamentoDaConta(ContaCobrancaGateway $cobranca): ?int
    {
        $conta = Conta::query()->find($cobranca->id_conta);
        $valor = $conta !== null ? min((float) $cobranca->amount, $conta->saldoDevedor()) : 0.0;

        if ($conta === null || $valor <= 0) {
            Log::warning('Cobrança online paga sem saldo devedor na conta; nenhum pagamento registrado.', [
                'id_cobranca_gateway' => $cobranca->id,
                'id_conta' => $cobranca->id_conta,
            ]);

            return null;
        }

        return $this->registrarPagamento->execute($conta, [
            'valor_pago' => round($valor, 2),
            'forma_pagamento' => $cobranca->method,
            'data_pagamento' => now()->toDateString(),
            'observacoes' => 'Pago online via Centralizador de Pagamentos ('.$cobranca->gateway_payment_uuid.').',
        ])->id;
    }

    /**
     * Conta quitada por outro meio (ex.: baixa manual): cancela a cobrança ainda em aberto no gateway.
     */
    private function cancelarSeContaQuitada(ContaCobrancaGateway $cobranca): void
    {
        $conta = Conta::query()->withoutInstituicaoScope()->find($cobranca->id_conta);

        if ($conta === null) {
            return;
        }

        $quitada = in_array($conta->status, ['pago', 'cancelado'], true) || $conta->saldoDevedor() <= 0;

        if (! $quitada) {
            return;
        }

        try {
            $this->gateway->cancelPayment($cobranca->gateway_payment_uuid);
            $cobranca->forceFill(['status' => ContaCobrancaGateway::STATUS_CANCELED])->save();
        } catch (Throwable $e) {
            Log::warning('Falha ao cancelar cobrança online de conta já quitada.', [
                'id_cobranca_gateway' => $cobranca->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function mapearStatus(mixed $remoto): ?string
    {
        return match ($remoto) {
            'pending' => ContaCobrancaGateway::STATUS_PENDING,
            'awaiting_payment' => ContaCobrancaGateway::STATUS_AWAITING_PAYMENT,
            'paid' => ContaCobrancaGateway::STATUS_PAID,
            'overdue' => ContaCobrancaGateway::STATUS_OVERDUE,
            'canceled', 'cancelled', 'refunded', 'deleted', 'expired' => ContaCobrancaGateway::STATUS_CANCELED,
            'failed' => ContaCobrancaGateway::STATUS_FAILED,
            default => null,
        };
    }
}
