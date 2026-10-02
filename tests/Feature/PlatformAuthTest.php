<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_retorna_is_super_admin_false_para_usuario_comum(): void
    {
        $user = User::factory()->create([
            'email' => 'comum-auth@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => false,
        ]);

        $instituicao = Instituicao::where('slug', 'default')->first();
        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_slug' => 'default',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_super_admin', false);
    }

    public function test_platform_login_retorna_token_sem_tenant_para_super_admin(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'super-auth@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);

        $response = $this->postJson('/api/platform/login', [
            'email' => $superAdmin->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_super_admin', true)
            ->assertJsonMissingPath('tenant');

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_platform_login_rejeita_usuario_comum(): void
    {
        $user = User::factory()->create([
            'email' => 'comum-platform@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => false,
        ]);

        $this->postJson('/api/platform/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function test_platform_login_rejeita_senha_errada(): void
    {
        User::factory()->create([
            'email' => 'super-errado@test.com',
            'senha' => Hash::make('password'),
            'is_super_admin' => true,
        ]);

        $this->postJson('/api/platform/login', [
            'email' => 'super-errado@test.com',
            'password' => 'outra-senha',
        ])->assertUnauthorized();
    }

    public function test_super_admin_sem_vinculo_nao_entra_no_painel_da_escola(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'super-escola@test.com',
            'senha' => Hash::make('password'),
            'is_super_admin' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => $superAdmin->email,
            'password' => 'password',
            'tenant_slug' => 'default',
        ])->assertForbidden();
    }

    public function test_endpoint_user_retorna_is_super_admin(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'me-auth@test.com',
            'senha' => Hash::make('password'),
            'is_super_admin' => true,
        ]);

        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.is_super_admin', true);
    }

    public function test_super_admin_acessa_platform_config(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_super_admin' => true,
            'role' => 'admin',
        ]));

        $this->getJson('/api/platform/config')
            ->assertOk()
            ->assertJsonPath('default_trial_days', 14)
            ->assertJsonStructure(['modulos_app']);
    }

    public function test_usuario_comum_nao_acessa_platform_config(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_super_admin' => false,
            'role' => 'admin',
        ]));

        $this->getJson('/api/platform/config')
            ->assertForbidden();
    }

    public function test_platform_config_respeita_env(): void
    {
        config(['platform.default_trial_days' => 21]);

        Sanctum::actingAs(User::factory()->create([
            'is_super_admin' => true,
            'role' => 'admin',
        ]));

        $this->getJson('/api/platform/config')
            ->assertOk()
            ->assertJsonPath('default_trial_days', 21);
    }
}
