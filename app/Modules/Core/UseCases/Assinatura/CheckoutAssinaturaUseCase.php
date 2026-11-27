<?php

namespace App\Modules\Core\UseCases\Assinatura;

use App\Modules\Core\Models\AssinaturaCobranca;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Core\Services\BillingPayloadBuilder;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Validation\ValidationException;

class CheckoutAssinaturaUseCase
{
    public function __construct(
        private readonly BillingGatewayClient $gateway,
        private readonly BillingPayloadBuilder $payloads,
    ) {}

    /**
     * @param  array{id_plano_assinatura: int, method: string}  $data
     */
    public function execute(User $user, array $data): AssinaturaCobranca
    {
        $instituicaoId = InstituicaoContext::id();

        if ($instituicaoId === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Informe a instituição no header X-Tenant-Slug.'],
            ]);
        }

        if (! $this->gateway->isAvailable()) {
            throw ValidationException::withMessages([
                'billing' => ['Assinatura online indisponível. Use a solicitação de plano para ativação manual.'],
            ]);
        }

        $plano = PlanoAssinatura::query()
            ->whereKey($data['id_plano_assinatura'])
            ->where('ativo', true)
            ->first();

        if ($plano === null) {
            throw ValidationException::withMessages([
                'id_plano_assinatura' => ['Plano inválido ou indisponível.'],
            ]);
        }

        if (! in_array($data['method'], AssinaturaCobranca::METODOS, true)) {
            throw ValidationException::withMessages([
                'method' => ['Método de pagamento inválido.'],
            ]);
        }

        $existente = AssinaturaCobranca::query()
            ->where('id_instituicao', $instituicaoId)
            ->whereIn('status', [...AssinaturaCobranca::STATUS_ABERTOS, ...AssinaturaCobranca::STATUS_VIGENTES])
            ->latest('id')
            ->first();

        if ($existente !== null) {
            $mensagem = $existente->isAberta()
                ? 'Já existe uma assinatura aguardando pagamento para esta escola.'
                : 'Esta escola já possui uma assinatura online ativa. Cancele-a antes de contratar outro plano.';

            throw ValidationException::withMessages(['id_plano_assinatura' => [$mensagem]]);
        }

        $instituicao = Instituicao::query()->findOrFail($instituicaoId);
        $customer = $this->payloads->customer($instituicao, $user);

        $faltantes = array_diff_key(
            ['name' => 1, 'email' => 1, 'cpf_cnpj' => 1],
            $customer,
        );

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'billing' => ['Complete o cadastro da escola (CNPJ, razão social/nome fantasia e e-mail) antes de assinar.'],
            ]);
        }

        $subscriberId = BillingPayloadBuilder::subscriberId($instituicao);
        $amount = (float) $plano->preco_mensal;

        $remota = $this->gateway->createSubscription([
            'subscriber_id' => $subscriberId,
            'customer_id' => $subscriberId,
            'amount' => $amount,
            'method' => $data['method'],
            'cycle' => AssinaturaCobranca::CYCLE_MONTHLY,
            'next_due_date' => now()->toDateString(),
            'description' => 'Assinatura Compasso - plano '.$plano->nome,
            'retain_on_platform' => true,
            'customer' => $customer,
        ]);

        return AssinaturaCobranca::query()->create([
            'id_instituicao' => $instituicao->id,
            'id_plano_assinatura' => $plano->id,
            'gateway_subscription_uuid' => $remota['id'] ?? $remota['uuid'] ?? null,
            'method' => $data['method'],
            'cycle' => AssinaturaCobranca::CYCLE_MONTHLY,
            'amount' => $amount,
            'status' => AssinaturaCobranca::STATUS_PENDING,
            'next_due_date' => $remota['next_due_date'] ?? now()->toDateString(),
            'customer_email' => $customer['email'],
        ])->load('plano');
    }
}
