<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Jobs\SyncAssinaturasGatewayJob;
use App\Modules\Core\Models\AssinaturaCobranca;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\PlatformSettings;
use Database\Seeders\PlanoAssinaturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingAssinaturaTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY = 'http://gateway.test';

    private const API_KEY = 'cpk_testkey1234567890';

    private const SUB_UUID = 'sub-uuid-0001';

    private const ACC_UUID = 'acc-uuid-0001';

    /**
     * @return array{instituicao: Instituicao, user: User, token: string}
     */
    private function criarInstituicaoComAdmin(bool $dadosCompletos = true): array
    {
        $dados = [
            'slug' => 'escola-billing',
            'nome_fantasia' => 'Escola Billing',
            'status' => Instituicao::STATUS_ATIVO,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
            'trial_ends_at' => now()->addDays(10),
            'trial_usa_padrao' => true,
        ];

        if ($dadosCompletos) {
            $dados += [
                'razao_social' => 'Escola Billing LTDA',
                'cnpj' => '11222333000181',
                'email_contato' => 'financeiro@escola-billing.test',
                'whatsapp' => '11999998888',
                'rua' => 'Rua das Flores',
                'numero' => '100',
                'bairro' => 'Centro',
                'cep' => '01001-000',
            ];
        }

        $instituicao = Instituicao::query()->create($dados);

        $user = User::factory()->create([
            'email' => 'admin@escola-billing.test',
            'senha' => Hash::make('password'),
            'role' => 'admin',
        ]);

        InstituicaoUsuario::query()->create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $token = $user->createToken('test');
        $token->accessToken->update(['id_instituicao' => $instituicao->id]);

        return ['instituicao' => $instituicao, 'user' => $user, 'token' => $token->plainTextToken];
    }

    private function habilitarBilling(bool $enabled = true): void
    {
        app(PlatformSettings::class)->setMany([
            PlatformSettings::BILLING_GATEWAY_URL => self::GATEWAY,
            PlatformSettings::BILLING_GATEWAY_API_KEY => self::API_KEY,
            PlatformSettings::BILLING_ENABLED => $enabled ? '1' : '0',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $payments
     */
    private function fakeGateway(string $subStatus = 'active', array $payments = [], string $accountStatus = 'pending'): void
    {
        Http::fake([
            self::GATEWAY.'/api/v1/ping' => Http::response(['success' => true, 'data' => ['authenticated' => true, 'system' => ['name' => 'Compasso']]]),
            self::GATEWAY.'/api/v1/subscriptions' => Http::response(['success' => true, 'data' => ['id' => self::SUB_UUID, 'status' => 'pending']], 202),
            self::GATEWAY.'/api/v1/subscriptions/'.self::SUB_UUID.'/cancel' => Http::response(['success' => true, 'data' => ['id' => self::SUB_UUID, 'status' => 'canceled']], 202),
            self::GATEWAY.'/api/v1/subscriptions/'.self::SUB_UUID.'*' => Http::response(['data' => [
                'id' => self::SUB_UUID,
                'status' => $subStatus,
                'payments' => $payments,
            ]]),
            self::GATEWAY.'/api/v1/subaccounts/'.self::ACC_UUID.'/payout-destination' => Http::response(['data' => ['id' => self::ACC_UUID, 'has_payout_destination' => true]]),
            self::GATEWAY.'/api/v1/subaccounts/'.self::ACC_UUID => Http::response(['data' => ['id' => self::ACC_UUID, 'status' => $accountStatus]]),
            self::GATEWAY.'/api/v1/subaccounts' => Http::response(['success' => true, 'data' => ['id' => self::ACC_UUID, 'status' => 'pending']], 202),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function headers(Instituicao $instituicao): array
    {
        return ['X-Tenant-Slug' => $instituicao->slug];
    }

    private function planoId(string $slug = 'profissional'): int
    {
        return (int) PlanoAssinatura::query()->where('slug', $slug)->value('id');
    }

    private function criarCheckout(array $ctx, string $method = 'pix'): AssinaturaCobranca
    {
        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/checkout', [
                'id_plano_assinatura' => $this->planoId(),
                'method' => $method,
            ])
            ->assertCreated();

        return AssinaturaCobranca::query()->firstOrFail();
    }

    public function test_checkout_cria_assinatura_no_gateway_e_registra_localmente(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway();
        $ctx = $this->criarInstituicaoComAdmin();

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/checkout', [
                'id_plano_assinatura' => $this->planoId(),
                'method' => 'pix',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', AssinaturaCobranca::STATUS_PENDING)
            ->assertJsonPath('data.method', 'pix')
            ->assertJsonPath('data.cycle', 'MONTHLY')
            ->assertJsonPath('data.plano.slug', 'profissional');

        $this->assertDatabaseHas('assinaturas_cobranca', [
            'id_instituicao' => $ctx['instituicao']->id,
            'id_plano_assinatura' => $this->planoId(),
            'gateway_subscription_uuid' => self::SUB_UUID,
            'method' => 'pix',
            'status' => AssinaturaCobranca::STATUS_PENDING,
            'customer_email' => 'admin@escola-billing.test',
        ]);

        $subscriberId = 'instituicao:'.$ctx['instituicao']->id;

        Http::assertSent(function (Request $request) use ($subscriberId): bool {
            if ($request->method() !== 'POST' || $request->url() !== self::GATEWAY.'/api/v1/subscriptions') {
                return false;
            }

            return $request->hasHeader('X-API-Key', self::API_KEY)
                && $request['retain_on_platform'] === true
                && $request['subscriber_id'] === $subscriberId
                && $request['customer_id'] === $subscriberId
                && (float) $request['amount'] === 199.9
                && $request['cycle'] === 'MONTHLY'
                && $request['customer']['name'] === 'Escola Billing LTDA'
                && $request['customer']['email'] === 'admin@escola-billing.test'
                && $request['customer']['cpf_cnpj'] === '11222333000181';
        });
    }

    public function test_checkout_rejeita_assinatura_pendente_duplicada(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway();
        $ctx = $this->criarInstituicaoComAdmin();
        $this->criarCheckout($ctx);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/checkout', [
                'id_plano_assinatura' => $this->planoId('basico'),
                'method' => 'boleto',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_plano_assinatura']);

        $this->assertSame(1, AssinaturaCobranca::query()->count());
    }

    public function test_checkout_valida_plano_e_metodo(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway();
        $ctx = $this->criarInstituicaoComAdmin();

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/checkout', [
                'id_plano_assinatura' => $this->planoId(),
                'method' => 'cheque',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['method']);

        PlanoAssinatura::query()->whereKey($this->planoId('basico'))->update(['ativo' => false]);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/checkout', [
                'id_plano_assinatura' => $this->planoId('basico'),
                'method' => 'pix',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_plano_assinatura']);
    }

    public function test_billing_desligado_bloqueia_checkout_e_mantem_fluxo_de_solicitacao(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling(enabled: false);
        Http::fake();
        $ctx = $this->criarInstituicaoComAdmin();

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->getJson('/api/assinatura/atual')
            ->assertOk()
            ->assertJsonPath('data.billing_disponivel', false)
            ->assertJsonPath('data.assinatura_status', Instituicao::ASSINATURA_TRIALING);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/checkout', [
                'id_plano_assinatura' => $this->planoId(),
                'method' => 'pix',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billing']);

        Http::assertNothingSent();
        $this->assertSame(0, AssinaturaCobranca::query()->count());

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/solicitar', ['id_plano_assinatura' => $this->planoId()])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pendente');
    }

    public function test_billing_sem_configuracao_nao_esta_disponivel(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        config(['services.billing.url' => null, 'services.billing.api_key' => null, 'services.billing.enabled' => true]);
        $ctx = $this->criarInstituicaoComAdmin();

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->getJson('/api/assinatura/atual')
            ->assertOk()
            ->assertJsonPath('data.billing_disponivel', false);
    }

    public function test_env_e_usado_como_fallback_quando_platform_esta_vazio(): void
    {
        config([
            'services.billing.url' => 'http://env-gateway.test/',
            'services.billing.api_key' => 'cpk_envkey0000',
            'services.billing.enabled' => 'true',
        ]);

        $settings = app(PlatformSettings::class);

        $this->assertSame('http://env-gateway.test', $settings->billingGatewayUrl());
        $this->assertSame('cpk_envkey0000', $settings->billingGatewayApiKey());
        $this->assertTrue($settings->billingEnabled());

        $settings->setMany([
            PlatformSettings::BILLING_GATEWAY_URL => self::GATEWAY,
            PlatformSettings::BILLING_ENABLED => '0',
        ]);

        $this->assertSame(self::GATEWAY, $settings->billingGatewayUrl());
        $this->assertFalse($settings->billingEnabled());
    }

    public function test_sync_com_pagamento_pago_ativa_instituicao_e_provisiona_subconta(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway('active', [
            ['id' => 'pay-1', 'status' => 'paid', 'due_date' => now()->toDateString(), 'invoice_url' => 'https://asaas.test/i/1'],
        ]);
        $ctx = $this->criarInstituicaoComAdmin();
        $checkout = $this->criarCheckout($ctx);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->getJson('/api/assinatura/checkout/'.$checkout->id.'?sync=1')
            ->assertOk()
            ->assertJsonPath('data.status', AssinaturaCobranca::STATUS_ACTIVE);

        $instituicao = $ctx['instituicao']->fresh();
        $this->assertSame(Instituicao::ASSINATURA_ACTIVE, $instituicao->assinatura_status);
        $this->assertSame($this->planoId(), $instituicao->id_plano_assinatura);
        $this->assertSame(now()->toDateString(), $instituicao->assinatura_inicia_em->toDateString());

        $this->assertSame(self::ACC_UUID, $instituicao->gateway_subaccount_uuid);
        $this->assertSame(Instituicao::SUBCONTA_PENDING, $instituicao->gateway_subaccount_status);

        $subscriberId = 'instituicao:'.$ctx['instituicao']->id;

        Http::assertSent(function (Request $request) use ($subscriberId): bool {
            return $request->method() === 'POST'
                && $request->url() === self::GATEWAY.'/api/v1/subaccounts'
                && $request['subscriber_id'] === $subscriberId
                && $request['cpf_cnpj'] === '11222333000181'
                && $request['company_type'] === 'LIMITED'
                && $request['postal_code'] === '01001000'
                && $request['province'] === 'Centro'
                && $request['email'] === 'financeiro@escola-billing.test'
                && $request['mobile_phone'] === '11999998888';
        });
    }

    public function test_sync_com_cobranca_pendente_aguarda_pagamento_e_expoe_fatura(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway('active', [
            ['id' => 'pay-1', 'status' => 'pending', 'due_date' => now()->toDateString(), 'invoice_url' => 'https://asaas.test/i/1'],
        ]);
        $ctx = $this->criarInstituicaoComAdmin();
        $checkout = $this->criarCheckout($ctx);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->getJson('/api/assinatura/checkout/'.$checkout->id.'?sync=1')
            ->assertOk()
            ->assertJsonPath('data.status', AssinaturaCobranca::STATUS_AWAITING_PAYMENT)
            ->assertJsonPath('data.invoice_url', 'https://asaas.test/i/1');

        $this->assertSame(Instituicao::ASSINATURA_TRIALING, $ctx['instituicao']->fresh()->assinatura_status);
        $this->assertNull($ctx['instituicao']->fresh()->gateway_subaccount_uuid);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->getJson('/api/assinatura/atual')
            ->assertOk()
            ->assertJsonPath('data.billing_disponivel', true)
            ->assertJsonPath('data.invoice_url', 'https://asaas.test/i/1')
            ->assertJsonPath('data.cobranca.status', AssinaturaCobranca::STATUS_AWAITING_PAYMENT);
    }

    public function test_pagamento_vencido_marca_past_due_sem_perder_plano(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway('active', [
            ['id' => 'pay-2', 'status' => 'overdue', 'due_date' => now()->subDays(3)->toDateString(), 'invoice_url' => 'https://asaas.test/i/2'],
            ['id' => 'pay-1', 'status' => 'paid', 'due_date' => now()->subDays(33)->toDateString()],
        ]);
        $ctx = $this->criarInstituicaoComAdmin();
        $ctx['instituicao']->update([
            'id_plano_assinatura' => $this->planoId(),
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
        ]);
        $checkout = $this->criarCheckoutDireto($ctx, AssinaturaCobranca::STATUS_ACTIVE);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->getJson('/api/assinatura/checkout/'.$checkout->id.'?sync=1')
            ->assertOk()
            ->assertJsonPath('data.status', AssinaturaCobranca::STATUS_PAST_DUE)
            ->assertJsonPath('data.invoice_url', 'https://asaas.test/i/2');

        $instituicao = $ctx['instituicao']->fresh();
        $this->assertSame(Instituicao::ASSINATURA_PAST_DUE, $instituicao->assinatura_status);
        $this->assertSame($this->planoId(), $instituicao->id_plano_assinatura);
    }

    private function criarCheckoutDireto(array $ctx, string $status): AssinaturaCobranca
    {
        return AssinaturaCobranca::query()->create([
            'id_instituicao' => $ctx['instituicao']->id,
            'id_plano_assinatura' => $this->planoId(),
            'gateway_subscription_uuid' => self::SUB_UUID,
            'method' => 'pix',
            'cycle' => 'MONTHLY',
            'amount' => 199.90,
            'status' => $status,
            'customer_email' => $ctx['user']->email,
        ]);
    }

    public function test_falha_ao_provisionar_subconta_nao_desfaz_ativacao(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway('active', [
            ['id' => 'pay-1', 'status' => 'paid', 'due_date' => now()->toDateString()],
        ]);
        $ctx = $this->criarInstituicaoComAdmin(dadosCompletos: false);
        $ctx['instituicao']->update(['cnpj' => '11222333000181']);
        $checkout = $this->criarCheckoutDireto($ctx, AssinaturaCobranca::STATUS_PENDING);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->getJson('/api/assinatura/checkout/'.$checkout->id.'?sync=1')
            ->assertOk()
            ->assertJsonPath('data.status', AssinaturaCobranca::STATUS_ACTIVE);

        $instituicao = $ctx['instituicao']->fresh();
        $this->assertSame(Instituicao::ASSINATURA_ACTIVE, $instituicao->assinatura_status);
        $this->assertSame(Instituicao::SUBCONTA_FAILED, $instituicao->gateway_subaccount_status);
        $this->assertStringContainsString('CEP', (string) $instituicao->gateway_subaccount_last_error);
    }

    public function test_job_sincroniza_assinatura_e_subconta_pendentes(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        // Stubs registrados primeiro têm precedência: subconta pending no 1º poll e active no 2º.
        Http::fake([
            self::GATEWAY.'/api/v1/subaccounts/'.self::ACC_UUID => Http::sequence()
                ->push(['data' => ['id' => self::ACC_UUID, 'status' => 'pending']])
                ->push(['data' => ['id' => self::ACC_UUID, 'status' => 'active']]),
        ]);
        $this->fakeGateway('active', [
            ['id' => 'pay-1', 'status' => 'paid', 'due_date' => now()->toDateString()],
        ]);
        $ctx = $this->criarInstituicaoComAdmin();
        $checkout = $this->criarCheckoutDireto($ctx, AssinaturaCobranca::STATUS_AWAITING_PAYMENT);

        // 1ª execução: ativa a assinatura e cria a subconta (pending).
        SyncAssinaturasGatewayJob::dispatchSync();

        $this->assertSame(AssinaturaCobranca::STATUS_ACTIVE, $checkout->fresh()->status);
        $this->assertSame(Instituicao::SUBCONTA_PENDING, $ctx['instituicao']->fresh()->gateway_subaccount_status);

        // 2ª execução: poll da subconta → active.
        SyncAssinaturasGatewayJob::dispatchSync();

        $instituicao = $ctx['instituicao']->fresh();
        $this->assertSame(Instituicao::SUBCONTA_ACTIVE, $instituicao->gateway_subaccount_status);
        $this->assertNull($instituicao->gateway_subaccount_last_error);
    }

    public function test_job_nao_faz_nada_com_billing_desligado(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling(enabled: false);
        Http::fake();
        $ctx = $this->criarInstituicaoComAdmin();
        $this->criarCheckoutDireto($ctx, AssinaturaCobranca::STATUS_PENDING);

        SyncAssinaturasGatewayJob::dispatchSync();

        Http::assertNothingSent();
    }

    public function test_cancelar_assinatura_online(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway();
        $ctx = $this->criarInstituicaoComAdmin();
        $ctx['instituicao']->update([
            'id_plano_assinatura' => $this->planoId(),
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
        ]);
        $checkout = $this->criarCheckoutDireto($ctx, AssinaturaCobranca::STATUS_ACTIVE);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/cancelar')
            ->assertOk()
            ->assertJsonPath('data.status', AssinaturaCobranca::STATUS_CANCELED);

        $this->assertSame(AssinaturaCobranca::STATUS_CANCELED, $checkout->fresh()->status);
        $this->assertSame(Instituicao::ASSINATURA_CANCELED, $ctx['instituicao']->fresh()->assinatura_status);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::GATEWAY.'/api/v1/subscriptions/'.self::SUB_UUID.'/cancel');

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/cancelar')
            ->assertUnprocessable();
    }

    public function test_erro_do_gateway_no_checkout_retorna_502_sem_registrar(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        Http::fake([self::GATEWAY.'/*' => Http::response(['success' => false, 'message' => 'Falha interna'], 500)]);
        $ctx = $this->criarInstituicaoComAdmin();

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/checkout', [
                'id_plano_assinatura' => $this->planoId(),
                'method' => 'pix',
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Falha interna');

        $this->assertSame(0, AssinaturaCobranca::query()->count());
    }

    public function test_checkout_de_outra_escola_nao_e_acessivel(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway();
        $ctx = $this->criarInstituicaoComAdmin();

        $outra = Instituicao::query()->create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
        ]);
        $alheio = AssinaturaCobranca::query()->create([
            'id_instituicao' => $outra->id,
            'id_plano_assinatura' => $this->planoId(),
            'gateway_subscription_uuid' => 'outro-uuid',
            'method' => 'pix',
            'cycle' => 'MONTHLY',
            'amount' => 99.90,
            'status' => AssinaturaCobranca::STATUS_PENDING,
        ]);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->getJson('/api/assinatura/checkout/'.$alheio->id)
            ->assertNotFound();
    }

    public function test_subconta_manual_permite_trial_e_rejeita_cancelada(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway();
        $ctx = $this->criarInstituicaoComAdmin();

        $this->assertSame(Instituicao::ASSINATURA_TRIALING, $ctx['instituicao']->assinatura_status);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/subconta')
            ->assertOk()
            ->assertJsonPath('data.status', Instituicao::SUBCONTA_PENDING);

        $this->assertSame(self::ACC_UUID, $ctx['instituicao']->fresh()->gateway_subaccount_uuid);

        $ctx['instituicao']->update([
            'assinatura_status' => Instituicao::ASSINATURA_CANCELED,
            'gateway_subaccount_uuid' => null,
            'gateway_subaccount_status' => Instituicao::SUBCONTA_NONE,
        ]);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->postJson('/api/assinatura/subconta')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assinatura']);
    }

    public function test_subconta_get_sincroniza_quando_pendente(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway(accountStatus: 'active');
        $ctx = $this->criarInstituicaoComAdmin();
        $ctx['instituicao']->update([
            'gateway_subaccount_uuid' => self::ACC_UUID,
            'gateway_subaccount_status' => Instituicao::SUBCONTA_PENDING,
        ]);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->getJson('/api/assinatura/subconta')
            ->assertOk()
            ->assertJsonPath('data.status', Instituicao::SUBCONTA_ACTIVE)
            ->assertJsonPath('data.ativa', true);
    }

    public function test_destino_de_saque_pix_exige_subconta_ativa(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway();
        $ctx = $this->criarInstituicaoComAdmin();
        $ctx['instituicao']->update([
            'gateway_subaccount_uuid' => self::ACC_UUID,
            'gateway_subaccount_status' => Instituicao::SUBCONTA_PENDING,
        ]);

        $payload = ['pix_key' => '11222333000181', 'pix_key_type' => 'CNPJ'];

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->putJson('/api/assinatura/subconta/saque', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subconta']);

        $ctx['instituicao']->update(['gateway_subaccount_status' => Instituicao::SUBCONTA_ACTIVE]);

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->putJson('/api/assinatura/subconta/saque', $payload)
            ->assertOk();

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && $r->url() === self::GATEWAY.'/api/v1/subaccounts/'.self::ACC_UUID.'/payout-destination'
            && $r['payout_type'] === 'pix'
            && $r['pix_key'] === '11222333000181'
            && $r['pix_key_type'] === 'CNPJ');

        $this->withToken($ctx['token'])
            ->withHeaders($this->headers($ctx['instituicao']))
            ->putJson('/api/assinatura/subconta/saque', ['pix_key' => 'x', 'pix_key_type' => 'INVALIDO'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pix_key_type']);
    }

    public function test_platform_provisiona_subconta_da_instituicao(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        $this->habilitarBilling();
        $this->fakeGateway();
        $ctx = $this->criarInstituicaoComAdmin();

        $super = User::factory()->create([
            'email' => 'super-billing@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
        Sanctum::actingAs($super);

        $this->postJson('/api/platform/instituicoes/'.$ctx['instituicao']->id.'/subconta')
            ->assertOk()
            ->assertJsonPath('data.status', Instituicao::SUBCONTA_PENDING);

        $this->assertSame(self::ACC_UUID, $ctx['instituicao']->fresh()->gateway_subaccount_uuid);
    }

    public function test_meios_de_cobranca_online_default_e_atualizacao(): void
    {
        $ctx = $this->criarInstituicaoComAdmin();
        $headers = $this->headers($ctx['instituicao']);

        $this->withToken($ctx['token'])->withHeaders($headers)
            ->getJson('/api/configuracao-cobranca-online')
            ->assertOk()
            ->assertJsonPath('data.meios', ['pix', 'boleto'])
            ->assertJsonPath('data.subconta_ativa', false)
            ->assertJsonPath('data.subconta_status', 'none');

        $this->withToken($ctx['token'])->withHeaders($headers)
            ->putJson('/api/configuracao-cobranca-online', ['meios' => ['pix', 'credit_card']])
            ->assertOk()
            ->assertJsonPath('data.meios', ['pix', 'credit_card']);

        $this->assertSame(['pix', 'credit_card'], $ctx['instituicao']->fresh()->meiosCobrancaOnline());
    }

    public function test_meios_de_cobranca_online_validam_lista(): void
    {
        $ctx = $this->criarInstituicaoComAdmin();
        $headers = $this->headers($ctx['instituicao']);

        $this->withToken($ctx['token'])->withHeaders($headers)
            ->putJson('/api/configuracao-cobranca-online', ['meios' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['meios']);

        $this->withToken($ctx['token'])->withHeaders($headers)
            ->putJson('/api/configuracao-cobranca-online', ['meios' => ['pix', 'cheque']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['meios.1']);

        $this->withToken($ctx['token'])->withHeaders($headers)
            ->putJson('/api/configuracao-cobranca-online', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['meios']);

        $this->assertSame(['pix', 'boleto'], $ctx['instituicao']->fresh()->meiosCobrancaOnline());
    }

    public function test_meios_de_cobranca_online_exigem_admin(): void
    {
        $ctx = $this->criarInstituicaoComAdmin();

        $professor = User::factory()->create([
            'email' => 'prof@escola-billing.test',
            'senha' => Hash::make('password'),
            'role' => 'professor',
        ]);
        InstituicaoUsuario::query()->create([
            'id_instituicao' => $ctx['instituicao']->id,
            'id_usuario' => $professor->id,
            'role' => 'professor',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);
        $token = $professor->createToken('test');
        $token->accessToken->update(['id_instituicao' => $ctx['instituicao']->id]);

        $this->withToken($token->plainTextToken)
            ->withHeaders($this->headers($ctx['instituicao']))
            ->getJson('/api/configuracao-cobranca-online')
            ->assertForbidden();
    }

    public function test_platform_configuracoes_billing_mascara_key_e_nao_sobrescreve_com_mascara(): void
    {
        $super = User::factory()->create([
            'email' => 'super-cfg@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
        Sanctum::actingAs($super);

        $this->putJson('/api/platform/configuracoes', [
            'billing_gateway_url' => self::GATEWAY.'/',
            'billing_gateway_api_key' => self::API_KEY,
            'billing_enabled' => true,
        ])->assertOk();

        $resposta = $this->getJson('/api/platform/configuracoes')
            ->assertOk()
            ->assertJsonPath('billing_gateway_url', self::GATEWAY)
            ->assertJsonPath('billing_enabled', true)
            ->assertJsonPath('billing_configured', true)
            ->assertJsonPath('billing_gateway_api_key_masked', 'cpk_****…7890');

        $this->assertStringNotContainsString(self::API_KEY, $resposta->getContent());
        $this->assertArrayNotHasKey('billing_gateway_api_key', $resposta->json());

        // Reenviar o valor mascarado ou vazio não altera a key salva.
        $this->putJson('/api/platform/configuracoes', [
            'billing_gateway_api_key' => 'cpk_****…7890',
            'billing_enabled' => false,
        ])->assertOk()->assertJsonPath('billing_enabled', false);

        $this->putJson('/api/platform/configuracoes', ['billing_gateway_api_key' => ''])->assertOk();

        $this->assertSame(self::API_KEY, app(PlatformSettings::class)->billingGatewayApiKey());

        // Nova key substitui.
        $this->putJson('/api/platform/configuracoes', ['billing_gateway_api_key' => 'cpk_novakey0000'])->assertOk();
        $this->assertSame('cpk_novakey0000', app(PlatformSettings::class)->billingGatewayApiKey());

        $this->putJson('/api/platform/configuracoes', ['billing_gateway_url' => 'nao-e-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_gateway_url']);
    }

    public function test_platform_testar_billing_faz_ping_com_key_salva_ou_digitada(): void
    {
        $this->habilitarBilling(enabled: false);
        $this->fakeGateway();
        $super = User::factory()->create([
            'email' => 'super-ping@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
        Sanctum::actingAs($super);

        $this->postJson('/api/platform/configuracoes/billing/testar')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('system', 'Compasso');

        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r->url() === self::GATEWAY.'/api/v1/ping'
            && $r->hasHeader('X-API-Key', self::API_KEY));

        Http::fake([
            'http://novo.test/api/v1/ping' => Http::response(['success' => false, 'message' => 'Unauthenticated'], 401),
        ]);

        $this->postJson('/api/platform/configuracoes/billing/testar', [
            'billing_gateway_url' => 'http://novo.test',
            'billing_gateway_api_key' => 'cpk_digitada',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false);

        Http::assertSent(fn (Request $r) => $r->url() === 'http://novo.test/api/v1/ping'
            && $r->hasHeader('X-API-Key', 'cpk_digitada'));
    }

    public function test_platform_testar_billing_sem_configuracao_retorna_422(): void
    {
        config(['services.billing.url' => null, 'services.billing.api_key' => null]);
        Http::fake();
        $super = User::factory()->create([
            'email' => 'super-sem-cfg@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
        Sanctum::actingAs($super);

        $this->postJson('/api/platform/configuracoes/billing/testar')
            ->assertUnprocessable()
            ->assertJsonPath('ok', false);

        Http::assertNothingSent();
    }
}
