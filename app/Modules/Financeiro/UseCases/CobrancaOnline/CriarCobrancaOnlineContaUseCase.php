<?php

namespace App\Modules\Financeiro\UseCases\CobrancaOnline;

use App\Modules\Core\Exceptions\BillingGatewayException;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaCobrancaGateway;
use App\Modules\Financeiro\Services\CobrancaOnlineCustomerBuilder;
use Illuminate\Validation\ValidationException;

/**
 * Cria (ou reaproveita) a cobrança online de uma conta a receber do aluno.
 *
 * O valor é cobrado em nome da subconta da escola: POST /payments SEM retain_on_platform.
 * Se já existir cobrança em aberto para a conta, ela é reaproveitada (para trocar o meio, cancele antes).
 */
class CriarCobrancaOnlineContaUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
        private readonly CobrancaOnlineCustomerBuilder $customers,
        private readonly SyncCobrancaOnlineContaUseCase $sync,
    ) {}

    /**
     * A cobrança retornada tem `wasRecentlyCreated = true` quando foi criada agora.
     */
    public function execute(Conta $conta, string $method): ContaCobrancaGateway
    {
        if (! $this->gateway->isAvailable()) {
            throw ValidationException::withMessages([
                'billing' => ['Cobrança online indisponível no momento.'],
            ]);
        }

        if (! in_array($conta->tipo, ['receita', 'receber'], true)) {
            throw ValidationException::withMessages([
                'conta' => ['Somente contas a receber podem ser cobradas online.'],
            ]);
        }

        $saldo = $conta->saldoDevedor();

        if ($conta->status === 'pago' || $conta->status === 'cancelado' || $saldo <= 0) {
            throw ValidationException::withMessages([
                'conta' => ['Esta conta não possui saldo em aberto para cobrança.'],
            ]);
        }

        $instituicao = Instituicao::query()->findOrFail($conta->id_instituicao);

        if (! $instituicao->subcontaAtiva()) {
            throw ValidationException::withMessages([
                'billing' => ['A escola ainda não possui uma subconta de recebimentos ativa.'],
            ]);
        }

        if (! in_array($method, ContaCobrancaGateway::METODOS, true)
            || ! in_array($method, $instituicao->meiosCobrancaOnline(), true)) {
            throw ValidationException::withMessages([
                'method' => ['Meio de pagamento indisponível para esta escola.'],
            ]);
        }

        $existente = ContaCobrancaGateway::query()
            ->where('id_conta', $conta->id)
            ->isAberta()
            ->latest('id')
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        $customer = $this->customers->customer($conta);

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer' => ['Cadastre nome, e-mail e CPF do responsável (ou do aluno) para gerar a cobrança online.'],
            ]);
        }

        $subscriberId = $this->customers->subscriberId($instituicao);
        $vencimento = $conta->data_vencimento?->toDateString();
        $hoje = now()->toDateString();

        $remota = $this->gateway->createPayment([
            'subscriber_id' => $subscriberId,
            'customer_id' => CobrancaOnlineCustomerBuilder::customerId((int) $conta->id_aluno),
            'amount' => $saldo,
            'method' => $method,
            'due_date' => ($vencimento === null || $vencimento < $hoje) ? $hoje : $vencimento,
            'description' => mb_substr((string) $conta->descricao, 0, 500),
            'customer' => $customer,
        ]);

        $cobranca = ContaCobrancaGateway::query()->create([
            'id_conta' => $conta->id,
            'id_instituicao' => $conta->id_instituicao,
            'gateway_payment_uuid' => $remota['id'] ?? $remota['uuid'] ?? null,
            'method' => $method,
            'amount' => $saldo,
            'status' => ContaCobrancaGateway::STATUS_PENDING,
        ]);

        $cobranca = $this->sync->aplicarRemota($cobranca, $remota);

        // O gateway gera o link/PIX de forma assíncrona; uma consulta com sync tenta preenchê-los já.
        if (blank($cobranca->invoice_url) && blank($cobranca->pix_copy_paste) && filled($cobranca->gateway_payment_uuid)) {
            try {
                $cobranca = $this->sync->execute($cobranca);
            } catch (BillingGatewayException) {
                // Mantém o estado local; o job agendado preenche depois.
            }
        }

        $cobranca->wasRecentlyCreated = true;

        return $cobranca;
    }
}
