<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\PersonalAccessToken;
use App\Modules\Core\Models\PlatformImpersonationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function criarSuperAdmin(): User
    {
        return User::factory()->create([
            'email' => 'super-imp@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
    }

    private function criarUsuarioNaInstituicao(Instituicao $instituicao, string $email = 'alvo@test.com'): User
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

    public function test_super_admin_impersona_usuario_da_escola(): void
    {
        $superAdmin = $this->criarSuperAdmin();
        Sanctum::actingAs($superAdmin);

        $instituicao = Instituicao::where('slug', 'default')->first();
        $alvo = $this->criarUsuarioNaInstituicao($instituicao, 'impersonar@test.com');

        $response = $this->postJson("/api/platform/instituicoes/{$instituicao->id}/impersonate", [
            'user_id' => $alvo->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'impersonar@test.com')
            ->assertJsonPath('tenant.slug', 'default')
            ->assertJsonPath('impersonation.super_admin.id', $superAdmin->id);

        $tokenId = explode('|', $response->json('token'), 2)[0];
        $token = PersonalAccessToken::find($tokenId);

        $this->assertSame($alvo->id, $token->tokenable_id);
        $this->assertSame($instituicao->id, $token->id_instituicao);
        $this->assertSame($superAdmin->id, $token->impersonator_user_id);

        $this->assertDatabaseHas('platform_impersonation_logs', [
            'id_super_admin' => $superAdmin->id,
            'id_usuario_alvo' => $alvo->id,
            'id_instituicao' => $instituicao->id,
            'token_id' => $token->id,
            'encerrado_em' => null,
        ]);
    }

    public function test_nao_impersona_usuario_fora_da_instituicao(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $instituicao = Instituicao::where('slug', 'default')->first();
        $outro = User::factory()->create(['email' => 'fora@test.com']);

        $this->postJson("/api/platform/instituicoes/{$instituicao->id}/impersonate", [
            'user_id' => $outro->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_nao_impersona_outro_super_admin(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $instituicao = Instituicao::where('slug', 'default')->first();
        $outroSuper = User::factory()->create([
            'email' => 'super2@test.com',
            'is_super_admin' => true,
            'role' => 'admin',
        ]);

        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $outroSuper->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $this->postJson("/api/platform/instituicoes/{$instituicao->id}/impersonate", [
            'user_id' => $outroSuper->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_stop_impersonation_restaura_super_admin(): void
    {
        $superAdmin = $this->criarSuperAdmin();
        Sanctum::actingAs($superAdmin);

        $instituicao = Instituicao::where('slug', 'default')->first();
        $alvo = $this->criarUsuarioNaInstituicao($instituicao, 'stop-imp@test.com');

        $impersonate = $this->postJson("/api/platform/instituicoes/{$instituicao->id}/impersonate", [
            'user_id' => $alvo->id,
        ])->assertOk();

        $impersonationToken = $impersonate->json('token');
        $logId = $impersonate->json('impersonation.log_id');

        auth()->forgetGuards();

        $this->withToken($impersonationToken)
            ->postJson('/api/platform/stop-impersonation')
            ->assertOk()
            ->assertJsonPath('user.email', $superAdmin->email);

        $this->assertNotNull(PlatformImpersonationLog::find($logId)->encerrado_em);
    }

    public function test_impersonacao_acessa_escola_suspensa(): void
    {
        $superAdmin = $this->criarSuperAdmin();
        Sanctum::actingAs($superAdmin);

        $instituicao = Instituicao::where('slug', 'default')->first();
        $instituicao->update(['status' => Instituicao::STATUS_SUSPENSO]);

        $alvo = $this->criarUsuarioNaInstituicao($instituicao, 'suspenso-imp@test.com');

        $token = $this->postJson("/api/platform/instituicoes/{$instituicao->id}/impersonate", [
            'user_id' => $alvo->id,
        ])->json('token');

        auth()->forgetGuards();

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/users')
            ->assertOk();
    }

    public function test_stop_impersonation_sem_sessao_ativa_retorna_422(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $this->postJson('/api/platform/stop-impersonation')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }
}
