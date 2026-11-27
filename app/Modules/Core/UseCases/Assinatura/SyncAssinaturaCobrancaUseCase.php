<?php

namespace App\Modules\Core\UseCases\Assinatura;

use App\Modules\Core\Exceptions\BillingGatewayException;
use App\Modules\Core\Models\AssinaturaCobranca;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Services\BillingGatewayClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sincroniza a assinatura local com o Centralizador (GET /subscriptions/{uuid}?sync=1).
 *
 * Mapeamento do status local:
 * - gateway failed            → failed
 * - gateway canceled/expired  → canceled
 * - gateway pending           → pending
 * - cobrança overdue          → past_due
 * - cobrança paid             → active (ativa o plano da escola e provisiona a subconta)
 * - demais                    → awaiting_payment
 */
class SyncAssinaturaCobrancaUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
        private readonly ProvisionarSubcontaEscolaUseCase $provisionarSubconta,
    ) {}

    public function execute(AssinaturaCobranca $assinatura): AssinaturaCobranca
    {
        if ($assinatura->isFinal() || blank($assinatura->gateway_subscription_uuid)) {
            return $assinatura;
        }

        $remota = $this->gateway->getSubscription($assinatura->gateway_subscription_uuid, sync: true);

        $payments = collect($remota['payments'] ?? [])
            ->filter(fn ($p) => is_array($p))
            ->sortByDesc(fn (array $p) => (string) ($p['due_date'] ?? ''))
            ->values();

        $aberta = $payments->first(fn (array $p) => in_array($p['status'] ?? null, ['pending', 'overdue'], true));
        $pagou = $payments->contains(fn (array $p) => ($p['status'] ?? null) === 'paid');
        $vencida = $payments->contains(fn (array $p) => ($p['status'] ?? null) === 'overdue');

        $status = match ($remota['status'] ?? null) {
            'failed' => AssinaturaCobranca::STATUS_FAILED,
            'canceled', 'expired' => AssinaturaCobranca::STATUS_CANCELED,
            'pending' => AssinaturaCobranca::STATUS_PENDING,
            default => match (true) {
                $vencida => AssinaturaCobranca::STATUS_PAST_DUE,
                $pagou => AssinaturaCobranca::STATUS_ACTIVE,
                default => AssinaturaCobranca::STATUS_AWAITING_PAYMENT,
            },
        };

        // Já estava vigente e o gateway ainda não listou cobranças: mantém o estado atual.
        if ($status === AssinaturaCobranca::STATUS_AWAITING_PAYMENT && $assinatura->isVigente() && $payments->isEmpty()) {
            $status = $assinatura->status;
        }

        $assinatura->forceFill([
            'status' => $status,
            'invoice_url' => $aberta['invoice_url'] ?? $aberta['bank_slip_url'] ?? null,
            'next_due_date' => $remota['next_due_date'] ?? $assinatura->next_due_date,
            'last_error' => $status === AssinaturaCobranca::STATUS_FAILED
                ? ($remota['last_error'] ?? 'Falha ao criar a assinatura no gateway.')
                : null,
        ])->save();
        $assinatura->touch();

        $instituicao = Instituicao::query()->find($assinatura->id_instituicao);

        if ($instituicao !== null) {
            if ($status === AssinaturaCobranca::STATUS_ACTIVE) {
                $this->ativarInstituicao($instituicao, $assinatura);
                $this->provisionarSubcontaSePreciso($instituicao);
            } elseif ($status === AssinaturaCobranca::STATUS_PAST_DUE) {
                $this->marcarEmAtraso($instituicao, $assinatura);
            }
        }

        return $assinatura->refresh()->load('plano');
    }

    /**
     * Mesma ideia de AprovarSolicitacaoAssinaturaUseCase: plano + status active + início da assinatura.
     * O observer de Instituicao invalida o cache de entitlements.
     */
    private function ativarInstituicao(Instituicao $instituicao, AssinaturaCobranca $assinatura): void
    {
        DB::transaction(function () use ($instituicao, $assinatura): void {
            $mesmoPlano = $instituicao->id_plano_assinatura === $assinatura->id_plano_assinatura;

            if ($mesmoPlano && $instituicao->assinatura_status === Instituicao::ASSINATURA_ACTIVE) {
                return;
            }

            // Regularização de atraso: mantém a data de início original.
            if ($mesmoPlano && $instituicao->assinatura_status === Instituicao::ASSINATURA_PAST_DUE) {
                $instituicao->assinatura_status = Instituicao::ASSINATURA_ACTIVE;
                $instituicao->save();

                return;
            }

            $instituicao->id_plano_assinatura = $assinatura->id_plano_assinatura;
            $instituicao->assinatura_status = Instituicao::ASSINATURA_ACTIVE;
            $instituicao->assinatura_inicia_em = now()->toDateString();
            $instituicao->save();
        });
    }

    private function marcarEmAtraso(Instituicao $instituicao, AssinaturaCobranca $assinatura): void
    {
        // Acesso continua; só reflete o atraso quando a escola já estava com este plano ativo.
        if (
            $instituicao->assinatura_status === Instituicao::ASSINATURA_ACTIVE
            && $instituicao->id_plano_assinatura === $assinatura->id_plano_assinatura
        ) {
            $instituicao->forceFill(['assinatura_status' => Instituicao::ASSINATURA_PAST_DUE])->save();
        }
    }

    private function provisionarSubcontaSePreciso(Instituicao $instituicao): void
    {
        if (
            filled($instituicao->gateway_subaccount_uuid)
            && $instituicao->gateway_subaccount_status !== Instituicao::SUBCONTA_FAILED
        ) {
            return;
        }

        try {
            $this->provisionarSubconta->execute($instituicao);
        } catch (ValidationException|BillingGatewayException $e) {
            // Falha na subconta não pode desfazer a ativação do plano; o erro fica registrado
            // na instituição (ou nos logs) e pode ser retentado via POST /assinatura/subconta.
            if ($e instanceof ValidationException) {
                $instituicao->forceFill([
                    'gateway_subaccount_status' => Instituicao::SUBCONTA_FAILED,
                    'gateway_subaccount_last_error' => collect($e->errors())->flatten()->first(),
                ])->save();
            }

            Log::warning('Falha ao provisionar subconta da escola.', [
                'id_instituicao' => $instituicao->id,
                'erro' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error('Erro inesperado ao provisionar subconta da escola.', [
                'id_instituicao' => $instituicao->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
