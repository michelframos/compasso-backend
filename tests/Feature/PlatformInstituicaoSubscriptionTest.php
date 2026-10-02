<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\PlanoAssinatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformInstituicaoSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function criarSuperAdmin(): User
    {
        return User::factory()->create([
            'email' => 'super-sub@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
    }

    private function criarUsuarioNaInstituicao(Instituicao $instituicao, string $email = 'user-sub@test.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'senha' => Hash::make('password'),
            'role' => 'admin',
        ]);

        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPayload(string $slug): array
    {
        return [
            'cnpj' => $this->cnpjValido($slug),
            'admin_nome' => "Admin {$slug}",
            'admin_email' => "admin@{$slug}.test",
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ];
    }

    public function test_cria_escola_com_trial_padrao(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $response = $this->postJson('/api/platform/instituicoes', array_merge([
            'slug' => 'escola-trial-padrao',
            'nome_fantasia' => 'Escola Trial Padrão',
        ], $this->adminPayload('escola-trial-padrao')));

        $response->assertCreated()
            ->assertJsonPath('data.trial_usa_padrao', true)
            ->assertJsonPath('data.assinatura_status', Instituicao::ASSINATURA_TRIALING)
            ->assertJsonPath('data.em_trial', true)
            ->assertJsonPath('data.acesso_bloqueado', false);

        $this->assertDatabaseHas('instituicoes', [
            'slug' => 'escola-trial-padrao',
            'trial_usa_padrao' => true,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
        ]);
    }

    public function test_cria_escola_com_trial_dias_customizado(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $response = $this->postJson('/api/platform/instituicoes', array_merge([
            'slug' => 'escola-trial-30',
            'nome_fantasia' => 'Escola Trial 30 dias',
            'trial_dias' => 30,
        ], $this->adminPayload('escola-trial-30')));

        $response->assertCreated()
            ->assertJsonPath('data.trial_usa_padrao', false)
            ->assertJsonPath('data.em_trial', true);

        $instituicao = Instituicao::where('slug', 'escola-trial-30')->first();
        $this->assertFalse($instituicao->trial_usa_padrao);
        $this->assertTrue($instituicao->trial_ends_at->greaterThan(now()->addDays(29)));
    }

    public function test_cria_escola_com_trial_ends_at_customizado(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $dataFim = now()->addDays(7)->toDateString();

        $response = $this->postJson('/api/platform/instituicoes', array_merge([
            'slug' => 'escola-trial-data',
            'nome_fantasia' => 'Escola Trial Data',
            'trial_ends_at' => $dataFim,
        ], $this->adminPayload('escola-trial-data')));

        $response->assertCreated()
            ->assertJsonPath('data.trial_usa_padrao', false);

        $instituicao = Instituicao::where('slug', 'escola-trial-data')->first();
        $this->assertSame($dataFim, $instituicao->trial_ends_at->toDateString());
    }

    public function test_atualiza_escola_com_novo_trial_dias(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $instituicao = Instituicao::create([
            'slug' => 'escola-atualizar-trial',
            'nome_fantasia' => 'Escola Atualizar',
            'status' => Instituicao::STATUS_ATIVO,
            'trial_ends_at' => now()->addDay(),
            'trial_usa_padrao' => true,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
        ]);

        $this->putJson("/api/platform/instituicoes/{$instituicao->id}", [
            'trial_dias' => 60,
        ])
            ->assertOk()
            ->assertJsonPath('data.trial_usa_padrao', false);

        $instituicao->refresh();
        $this->assertTrue($instituicao->trial_ends_at->greaterThan(now()->addDays(59)));
    }

    public function test_resource_exibe_plano_e_flags_assinatura(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $plano = PlanoAssinatura::create([
            'slug' => 'pro',
            'nome' => 'Pro',
            'preco_mensal' => 199,
            'ativo' => true,
        ]);

        $instituicao = Instituicao::create([
            'slug' => 'escola-com-plano',
            'nome_fantasia' => 'Escola com Plano',
            'status' => Instituicao::STATUS_ATIVO,
            'id_plano_assinatura' => $plano->id,
            'trial_ends_at' => now()->addDays(10),
            'trial_usa_padrao' => false,
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
        ]);

        $this->getJson("/api/platform/instituicoes/{$instituicao->id}")
            ->assertOk()
            ->assertJsonPath('data.plano.slug', 'pro')
            ->assertJsonPath('data.acesso_bloqueado', false);
    }

    public function test_login_bloqueado_quando_instituicao_suspensa(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $instituicao->update(['status' => Instituicao::STATUS_SUSPENSO]);

        $user = $this->criarUsuarioNaInstituicao($instituicao, 'suspenso@test.com');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_slug' => 'default',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'institution_suspended');
    }

    public function test_login_bloqueado_quando_trial_expirado(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $instituicao->update([
            'trial_ends_at' => now()->subDay(),
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
        ]);

        $user = $this->criarUsuarioNaInstituicao($instituicao, 'trial-exp@test.com');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_slug' => 'default',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_inactive');
    }

    public function test_login_permitido_com_assinatura_ativa_mesmo_apos_trial(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $instituicao->update([
            'trial_ends_at' => now()->subDay(),
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
        ]);

        $user = $this->criarUsuarioNaInstituicao($instituicao, 'ativo@test.com');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_slug' => 'default',
        ])
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'default');
    }

    public function test_switch_bloqueado_quando_assinatura_cancelada(): void
    {
        $instituicao = Instituicao::create([
            'slug' => 'escola-cancelada',
            'nome_fantasia' => 'Escola Cancelada',
            'status' => Instituicao::STATUS_ATIVO,
            'trial_ends_at' => now()->subDay(),
            'assinatura_status' => Instituicao::ASSINATURA_CANCELED,
        ]);

        $user = $this->criarUsuarioNaInstituicao($instituicao, 'cancelada@test.com');
        Sanctum::actingAs($user);

        $this->postJson('/api/instituicoes/switch', [
            'tenant_slug' => 'escola-cancelada',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_canceled');
    }
}
