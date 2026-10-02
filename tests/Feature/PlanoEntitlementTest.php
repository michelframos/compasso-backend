<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\CachedPlanoEntitlementResolver;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanoEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        InstituicaoContext::clear();
        parent::tearDown();
    }

    private function criarAdmin(Instituicao $instituicao, string $email): array
    {
        $user = User::factory()->create([
            'email' => $email,
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

        return [$user, $token->plainTextToken];
    }

    public function test_super_admin_cria_plano_com_modulos(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_super_admin' => true,
            'role' => 'admin',
        ]));

        $this->postJson('/api/platform/planos', [
            'slug' => 'com-modulos',
            'nome' => 'Com Módulos',
            'preco_mensal' => 120,
            'limite_alunos' => 50,
            'modulos' => ['leads', 'financeiro'],
            'ativo' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.modulos', ['leads', 'financeiro']);

        $this->assertDatabaseHas('planos_assinatura', [
            'slug' => 'com-modulos',
        ]);
    }

    public function test_rejeita_modulo_invalido_no_plano(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_super_admin' => true,
            'role' => 'admin',
        ]));

        $this->postJson('/api/platform/planos', [
            'slug' => 'invalido',
            'nome' => 'Inválido',
            'preco_mensal' => 10,
            'modulos' => ['nao-existe'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['modulos.0']);
    }

    public function test_platform_config_lista_modulos_app(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_super_admin' => true,
            'role' => 'admin',
        ]));

        $this->getJson('/api/platform/config')
            ->assertOk()
            ->assertJsonStructure([
                'default_trial_days',
                'modulos_app' => [['key', 'label', 'descricao']],
            ]);
    }

    public function test_trial_libera_modulo_financeiro(): void
    {
        $instituicao = Instituicao::query()->create([
            'slug' => 'escola-trial-ent',
            'nome_fantasia' => 'Escola Trial Ent',
            'status' => Instituicao::STATUS_ATIVO,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
            'trial_ends_at' => now()->addDays(7),
        ]);

        [, $token] = $this->criarAdmin($instituicao, 'admin-trial-ent@test.com');

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', $instituicao->slug)
            ->getJson('/api/contas')
            ->assertOk();
    }

    public function test_plano_ativo_sem_financeiro_bloqueia_api(): void
    {
        $plano = PlanoAssinatura::query()->create([
            'slug' => 'so-leads',
            'nome' => 'Só Leads',
            'preco_mensal' => 50,
            'limite_alunos' => 20,
            'modulos' => ['leads'],
            'ativo' => true,
        ]);

        $instituicao = Instituicao::query()->create([
            'slug' => 'escola-sem-fin',
            'nome_fantasia' => 'Escola Sem Fin',
            'status' => Instituicao::STATUS_ATIVO,
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            'id_plano_assinatura' => $plano->id,
            'assinatura_inicia_em' => now()->toDateString(),
        ]);

        [, $token] = $this->criarAdmin($instituicao, 'admin-sem-fin@test.com');

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', $instituicao->slug)
            ->getJson('/api/contas')
            ->assertForbidden()
            ->assertJsonPath('code', 'modulo_nao_incluido');

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', $instituicao->slug)
            ->getJson('/api/leads')
            ->assertOk();
    }

    public function test_limite_alunos_bloqueia_terceiro_cadastro(): void
    {
        $plano = PlanoAssinatura::query()->create([
            'slug' => 'limite-2',
            'nome' => 'Limite 2',
            'preco_mensal' => 40,
            'limite_alunos' => 2,
            'modulos' => [],
            'ativo' => true,
        ]);

        $instituicao = Instituicao::query()->create([
            'slug' => 'escola-limite',
            'nome_fantasia' => 'Escola Limite',
            'status' => Instituicao::STATUS_ATIVO,
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            'id_plano_assinatura' => $plano->id,
            'assinatura_inicia_em' => now()->toDateString(),
        ]);

        [, $token] = $this->criarAdmin($instituicao, 'admin-limite@test.com');

        InstituicaoContext::setFromModel($instituicao);
        foreach (['a', 'b'] as $suffix) {
            Aluno::query()->create([
                'id_usuario' => User::factory()->create([
                    'email' => "aluno-{$suffix}@limite.test",
                    'role' => 'aluno',
                ])->id,
                'id_instituicao' => $instituicao->id,
            ]);
        }
        InstituicaoContext::clear();

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', $instituicao->slug)
            ->postJson('/api/alunos', [
                'nome' => 'Aluno Extra',
                'email' => 'aluno-extra@limite.test',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limite_alunos']);
    }

    public function test_public_planos_inclui_labels_dos_modulos_nas_features(): void
    {
        PlanoAssinatura::query()->create([
            'slug' => 'com-features',
            'nome' => 'Com Features',
            'descricao' => 'Suporte por e-mail',
            'preco_mensal' => 80,
            'limite_alunos' => 40,
            'modulos' => ['financeiro', 'leads'],
            'ativo' => true,
        ]);

        $this->getJson('/api/public/planos')
            ->assertOk()
            ->assertJsonPath('data.0.features.0', 'Até 40 alunos')
            ->assertJsonPath('data.0.features.1', 'Financeiro')
            ->assertJsonPath('data.0.features.2', 'Leads')
            ->assertJsonPath('data.0.features.3', 'Suporte por e-mail');
    }

    private function criarInstituicaoComPlano(array $modulos): array
    {
        $plano = PlanoAssinatura::query()->create([
            'slug' => 'cache-'.uniqid(),
            'nome' => 'Plano Cache',
            'preco_mensal' => 10,
            'limite_alunos' => 5,
            'modulos' => $modulos,
            'ativo' => true,
        ]);

        $instituicao = Instituicao::query()->create([
            'slug' => 'escola-cache-'.uniqid(),
            'nome_fantasia' => 'Escola Cache',
            'status' => Instituicao::STATUS_ATIVO,
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            'id_plano_assinatura' => $plano->id,
        ]);

        return [$plano, $instituicao];
    }

    public function test_interface_resolve_para_o_proxy_com_cache(): void
    {
        $this->assertInstanceOf(
            CachedPlanoEntitlementResolver::class,
            app(PlanoEntitlementResolverInterface::class)
        );
    }

    public function test_resolve_usa_cache_ate_o_plano_mudar(): void
    {
        [$plano, $instituicao] = $this->criarInstituicaoComPlano(['leads']);
        $resolver = app(PlanoEntitlementResolverInterface::class);

        $this->assertSame(['leads'], $resolver->modulos($instituicao));

        // Alteração direta no banco não dispara observers: o valor continua vindo do cache.
        DB::table('planos_assinatura')->where('id', $plano->id)->update(['modulos' => json_encode(['financeiro'])]);
        $this->assertSame(['leads'], $resolver->modulos($instituicao->fresh()));

        $plano->fresh()->update(['modulos' => ['instrumentos']]);
        $this->assertSame(['instrumentos'], $resolver->modulos($instituicao->fresh()));
    }

    public function test_alterar_instituicao_invalida_o_cache(): void
    {
        [, $instituicao] = $this->criarInstituicaoComPlano(['leads']);
        $resolver = app(PlanoEntitlementResolverInterface::class);

        $this->assertSame(['leads'], $resolver->modulos($instituicao));

        $instituicao->update(['assinatura_status' => Instituicao::ASSINATURA_CANCELED]);

        $this->assertSame([], $resolver->modulos($instituicao->fresh()));
    }

    public function test_cache_do_trial_nao_sobrevive_ao_fim_do_trial(): void
    {
        $instituicao = Instituicao::query()->create([
            'slug' => 'escola-trial-cache',
            'nome_fantasia' => 'Escola Trial Cache',
            'status' => Instituicao::STATUS_ATIVO,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
            'trial_ends_at' => now()->addSeconds(30),
        ]);
        $resolver = app(PlanoEntitlementResolverInterface::class);

        $this->assertTrue($resolver->resolve($instituicao)['em_trial']);

        $this->travel(31)->seconds();

        $this->assertFalse($resolver->resolve($instituicao->fresh())['em_trial']);
    }

    public function test_login_tenant_traz_modulos_efetivos(): void
    {
        $plano = PlanoAssinatura::query()->create([
            'slug' => 'login-mods',
            'nome' => 'Login Mods',
            'preco_mensal' => 10,
            'modulos' => ['instrumentos'],
            'ativo' => true,
        ]);

        $instituicao = Instituicao::query()->create([
            'slug' => 'escola-login-mods',
            'nome_fantasia' => 'Escola Login Mods',
            'status' => Instituicao::STATUS_ATIVO,
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            'id_plano_assinatura' => $plano->id,
        ]);

        $user = User::factory()->create([
            'email' => 'login-mods@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
        ]);

        InstituicaoUsuario::query()->create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $this->postJson('/api/login', [
            'email' => 'login-mods@test.com',
            'password' => 'password',
            'tenant_slug' => $instituicao->slug,
        ])
            ->assertOk()
            ->assertJsonPath('tenant.modulos', ['instrumentos'])
            ->assertJsonPath('tenant.em_trial', false);
    }
}
