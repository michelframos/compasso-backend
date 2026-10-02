<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoreInstituicaoScopedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        InstituicaoContext::clear();

        parent::tearDown();
    }

    private function criarAdminNaInstituicao(Instituicao $instituicao, string $email = 'admin@test.com'): User
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

    private function autenticarComo(User $user, Instituicao $instituicao): string
    {
        $token = $user->createToken('test');
        $token->accessToken->update(['id_instituicao' => $instituicao->id]);

        return $token->plainTextToken;
    }

    public function test_users_sem_tenant_slug_retorna_422(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao);
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->getJson('/api/users')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Informe a instituição no header X-Tenant-Slug.');
    }

    public function test_users_lista_apenas_usuarios_da_instituicao_ativa(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-b',
            'nome_fantasia' => 'Escola B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $adminDefault = $this->criarAdminNaInstituicao($default, 'admin-default@test.com');
        $usuarioDefault = $this->criarAdminNaInstituicao($default, 'user-default@test.com');
        $usuarioOutra = $this->criarAdminNaInstituicao($outra, 'user-outra@test.com');

        $token = $this->autenticarComo($adminDefault, $default);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/users');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertTrue($emails->contains($usuarioDefault->email));
        $this->assertFalse($emails->contains($usuarioOutra->email));
    }

    public function test_configuracao_empresa_get_retorna_instituicao_ativa(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-config',
            'nome_fantasia' => 'Nome Escola Config',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($outra, 'admin-config@test.com');
        $token = $this->autenticarComo($admin, $outra);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'escola-config')
            ->getJson('/api/configuracao-empresa');

        $response->assertOk()
            ->assertJsonPath('nome_fantasia', 'Nome Escola Config')
            ->assertJsonPath('slug', 'escola-config');
    }

    public function test_configuracao_empresa_put_atualiza_instituicao_ativa(): void
    {
        $instituicao = Instituicao::create([
            'slug' => 'escola-update',
            'nome_fantasia' => 'Antes',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-update@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'escola-update')
            ->putJson('/api/configuracao-empresa', [
                'nome_fantasia' => 'Depois',
                'cnpj' => $this->cnpjValido('escola-update'),
            ])
            ->assertOk()
            ->assertJsonPath('nome_fantasia', 'Depois');

        $this->assertDatabaseHas('instituicoes', [
            'id' => $instituicao->id,
            'nome_fantasia' => 'Depois',
            'cnpj' => $this->cnpjValido('escola-update'),
        ]);

        $default = Instituicao::where('slug', 'default')->first();
        $this->assertNotSame('Depois', $default->fresh()->nome_fantasia);
    }

    public function test_criar_usuario_vincula_a_instituicao_ativa(): void
    {
        $instituicao = Instituicao::create([
            'slug' => 'escola-create-user',
            'nome_fantasia' => 'Escola Create User',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-create@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $cpf = '52998224725';
        $email = 'novo-user@test.com';

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'escola-create-user')
            ->postJson('/api/users', [
                'nome' => 'Novo User',
                'email' => $email,
                'password' => 'password',
                'password_confirmation' => 'password',
                'cpf' => $cpf,
                'role' => 'secretaria',
            ])
            ->assertCreated();

        $userId = User::where('email', $email)->value('id');

        $this->assertDatabaseHas('instituicoes_usuarios', [
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $userId,
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);
    }
}
