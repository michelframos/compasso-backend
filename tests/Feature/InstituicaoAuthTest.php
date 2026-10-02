<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\PersonalAccessToken;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstituicaoAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'resolve.instituicao'])
            ->get('/_test/token-instituicao', fn () => response()->json(['ok' => true]));
    }

    protected function tearDown(): void
    {
        InstituicaoContext::clear();

        parent::tearDown();
    }

    private function criarUsuarioComInstituicao(string $email, string $role = 'admin'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'senha' => Hash::make('password'),
            'role' => $role,
        ]);

        $instituicao = Instituicao::where('slug', 'default')->first();

        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => $role,
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        return $user;
    }

    public function test_login_com_cnpj_retorna_token_tenant_e_tenants(): void
    {
        $user = $this->criarUsuarioComInstituicao('login1@test.com');

        $cnpj = $this->cnpjValido('default');
        Instituicao::where('slug', 'default')->first()->update(['cnpj' => $cnpj]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_cnpj' => preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $cnpj),
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data', 'token', 'tenant', 'tenants'])
            ->assertJsonPath('tenant.slug', 'default');

        $tokenId = explode('|', $response->json('token'), 2)[0];
        $token = PersonalAccessToken::find($tokenId);

        $this->assertNotNull($token->id_instituicao);
    }

    public function test_login_sem_cnpj_retorna_422(): void
    {
        $user = $this->criarUsuarioComInstituicao('sem-cnpj@test.com');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_cnpj']);
    }

    public function test_login_com_cnpj_inexistente_retorna_404(): void
    {
        $user = $this->criarUsuarioComInstituicao('cnpj-404@test.com');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_cnpj' => $this->cnpjValido('nao-existe'),
        ])->assertNotFound();
    }

    public function test_login_com_cnpj_de_escola_sem_vinculo_retorna_403(): void
    {
        $user = $this->criarUsuarioComInstituicao('cnpj-403@test.com');

        Instituicao::create([
            'slug' => 'escola-sem-vinculo',
            'nome_fantasia' => 'Escola Sem Vínculo',
            'cnpj' => $this->cnpjValido('escola-sem-vinculo'),
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_cnpj' => $this->cnpjValido('escola-sem-vinculo'),
        ])->assertForbidden();
    }

    public function test_login_com_senha_errada_e_cnpj_valido_retorna_401(): void
    {
        $user = $this->criarUsuarioComInstituicao('senha-errada@test.com');

        $cnpj = $this->cnpjValido('default');
        Instituicao::where('slug', 'default')->first()->update(['cnpj' => $cnpj]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'errada',
            'tenant_cnpj' => $cnpj,
        ])->assertUnauthorized();
    }

    public function test_login_com_tenant_slug_seleciona_instituicao(): void
    {
        $user = $this->criarUsuarioComInstituicao('slug@test.com');

        Instituicao::create([
            'slug' => 'escola-b',
            'nome_fantasia' => 'Escola B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $outra = Instituicao::where('slug', 'escola-b')->first();

        InstituicaoUsuario::create([
            'id_instituicao' => $outra->id,
            'id_usuario' => $user->id,
            'role' => 'professor',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_slug' => 'escola-b',
        ]);

        $response->assertOk()->assertJsonPath('tenant.slug', 'escola-b');
    }

    public function test_login_com_slug_sem_acesso_retorna_403(): void
    {
        $user = $this->criarUsuarioComInstituicao('forbidden@test.com');

        Instituicao::create([
            'slug' => 'escola-c',
            'nome_fantasia' => 'Escola C',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_slug' => 'escola-c',
        ])->assertForbidden();
    }

    public function test_instituicoes_mine_lista_instituicoes_do_usuario(): void
    {
        $user = $this->criarUsuarioComInstituicao('mine@test.com');

        Sanctum::actingAs($user);

        $this->getJson('/api/instituicoes/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'default');
    }

    public function test_instituicoes_switch_emite_novo_token(): void
    {
        $user = $this->criarUsuarioComInstituicao('switch@test.com');

        $outra = Instituicao::create([
            'slug' => 'escola-switch',
            'nome_fantasia' => 'Escola Switch',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        InstituicaoUsuario::create([
            'id_instituicao' => $outra->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/instituicoes/switch', [
            'tenant_slug' => 'escola-switch',
        ]);

        $response->assertOk()
            ->assertJsonPath('tenant.slug', 'escola-switch')
            ->assertJsonStructure(['token', 'tenant', 'tenants']);

        $tokenId = explode('|', $response->json('token'), 2)[0];
        $token = PersonalAccessToken::find($tokenId);

        $this->assertSame($outra->id, $token->id_instituicao);
    }

    public function test_token_rejeitado_com_header_de_outra_instituicao(): void
    {
        $user = $this->criarUsuarioComInstituicao('token@test.com');

        $outra = Instituicao::create([
            'slug' => 'outra-token',
            'nome_fantasia' => 'Outra',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        InstituicaoUsuario::create([
            'id_instituicao' => $outra->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'tenant_slug' => 'default',
        ])->assertOk();

        $tokenId = explode('|', $login->json('token'), 2)[0];
        $token = PersonalAccessToken::find($tokenId);
        $this->assertSame(
            (int) Instituicao::where('slug', 'default')->value('id'),
            (int) $token->id_instituicao
        );

        $this->withToken($login->json('token'))
            ->withHeader('X-Tenant-Slug', 'outra-token')
            ->getJson('/_test/token-instituicao')
            ->assertForbidden();
    }
}
