<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformInstituicaoTest extends TestCase
{
    use RefreshDatabase;

    private function criarSuperAdmin(): User
    {
        return User::factory()->create([
            'email' => 'super@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
    }

    private function criarUsuarioComum(): User
    {
        return User::factory()->create([
            'email' => 'comum@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => false,
        ]);
    }

    public function test_usuario_comum_nao_acessa_platform_instituicoes(): void
    {
        Sanctum::actingAs($this->criarUsuarioComum());

        $this->getJson('/api/platform/instituicoes')
            ->assertForbidden()
            ->assertJsonPath('message', 'Acesso restrito à plataforma.');
    }

    public function test_super_admin_lista_instituicoes(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $response = $this->getJson('/api/platform/instituicoes');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'nome_fantasia', 'status']]]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_super_admin_cria_instituicao(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $response = $this->postJson('/api/platform/instituicoes', [
            'slug' => 'escola-nova',
            'nome_fantasia' => 'Escola Nova',
            'razao_social' => 'Escola Nova LTDA',
            'cnpj' => $this->cnpjValido('escola-nova'),
            'admin_nome' => 'Admin Escola Nova',
            'admin_email' => 'admin@escola-nova.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'escola-nova')
            ->assertJsonPath('data.nome_fantasia', 'Escola Nova')
            ->assertJsonPath('data.status', Instituicao::STATUS_ATIVO);

        $this->assertDatabaseHas('instituicoes', [
            'slug' => 'escola-nova',
            'nome_fantasia' => 'Escola Nova',
        ]);

        $this->assertDatabaseHas('usuarios', [
            'email' => 'admin@escola-nova.com',
            'role' => 'admin',
        ]);

        $instituicaoId = $response->json('data.id');

        $this->assertDatabaseHas('instituicoes_usuarios', [
            'id_instituicao' => $instituicaoId,
            'role' => 'admin',
            'status' => 'a',
        ]);
    }

    public function test_admin_criado_pode_logar_na_escola(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $this->postJson('/api/platform/instituicoes', [
            'slug' => 'escola-login',
            'nome_fantasia' => 'Escola Login',
            'cnpj' => $this->cnpjValido('escola-login'),
            'admin_nome' => 'Gestor',
            'admin_email' => 'gestor@escola-login.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ])->assertCreated();

        $instituicao = Instituicao::where('slug', 'escola-login')->first();
        $user = User::where('email', 'gestor@escola-login.com')->first();

        $this->assertNotNull($instituicao);
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('Password1!', $user->senha));
        $this->assertDatabaseHas('instituicoes_usuarios', [
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => 'a',
        ]);
    }

    public function test_cria_instituicao_exige_dados_do_admin(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $this->postJson('/api/platform/instituicoes', [
            'slug' => 'sem-admin',
            'nome_fantasia' => 'Sem Admin',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj', 'admin_nome', 'admin_email', 'admin_password']);
    }

    public function test_cria_instituicao_rejeita_cnpj_invalido(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $this->postJson('/api/platform/instituicoes', [
            'slug' => 'cnpj-invalido',
            'nome_fantasia' => 'CNPJ Inválido',
            'cnpj' => '11.111.111/1111-11',
            'admin_nome' => 'Admin',
            'admin_email' => 'admin@cnpj-invalido.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj']);
    }

    public function test_cria_instituicao_rejeita_cnpj_duplicado_mesmo_com_mascara(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $cnpj = $this->cnpjValido('cnpj-dup');
        $cnpjComMascara = preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $cnpj);

        $this->postJson('/api/platform/instituicoes', [
            'slug' => 'cnpj-dup-a',
            'nome_fantasia' => 'Dup A',
            'cnpj' => $cnpj,
            'admin_nome' => 'Admin A',
            'admin_email' => 'a@cnpj-dup.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ])->assertCreated();

        $this->postJson('/api/platform/instituicoes', [
            'slug' => 'cnpj-dup-b',
            'nome_fantasia' => 'Dup B',
            'cnpj' => $cnpjComMascara,
            'admin_nome' => 'Admin B',
            'admin_email' => 'b@cnpj-dup.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj']);
    }

    public function test_cnpj_e_salvo_sem_mascara(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $cnpj = $this->cnpjValido('cnpj-mascara');
        $cnpjComMascara = preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $cnpj);

        $this->postJson('/api/platform/instituicoes', [
            'slug' => 'cnpj-mascara',
            'nome_fantasia' => 'Com Máscara',
            'cnpj' => $cnpjComMascara,
            'admin_nome' => 'Admin',
            'admin_email' => 'admin@cnpj-mascara.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ])->assertCreated();

        $this->assertDatabaseHas('instituicoes', ['slug' => 'cnpj-mascara', 'cnpj' => $cnpj]);
    }

    public function test_super_admin_atualiza_status_instituicao(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $instituicao = Instituicao::create([
            'slug' => 'escola-desativar',
            'nome_fantasia' => 'Escola Desativar',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $response = $this->putJson("/api/platform/instituicoes/{$instituicao->id}", [
            'status' => Instituicao::STATUS_INATIVO,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', Instituicao::STATUS_INATIVO);

        $this->assertDatabaseHas('instituicoes', [
            'id' => $instituicao->id,
            'status' => Instituicao::STATUS_INATIVO,
        ]);
    }

    public function test_super_admin_exclui_instituicao_soft_delete(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $instituicao = Instituicao::create([
            'slug' => 'escola-remover',
            'nome_fantasia' => 'Escola Remover',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $this->deleteJson("/api/platform/instituicoes/{$instituicao->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('instituicoes', ['id' => $instituicao->id]);
    }

    public function test_super_admin_lista_com_with_trashed(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $instituicao = Instituicao::create([
            'slug' => 'escola-trashed',
            'nome_fantasia' => 'Escola Trashed',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $instituicao->delete();

        $response = $this->getJson('/api/platform/instituicoes?with_trashed=1');

        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains('escola-trashed'));
    }

    public function test_permite_mesmo_email_admin_em_escolas_diferentes(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $email = 'admin@compartilhado.com';
        $senha = 'Password1!';

        $this->postJson('/api/platform/instituicoes', [
            'slug' => 'escola-um',
            'nome_fantasia' => 'Escola Um',
            'cnpj' => $this->cnpjValido('escola-um'),
            'admin_nome' => 'Admin Um',
            'admin_email' => $email,
            'admin_password' => $senha,
            'admin_password_confirmation' => $senha,
        ])->assertCreated();

        $this->postJson('/api/platform/instituicoes', [
            'slug' => 'escola-dois',
            'nome_fantasia' => 'Escola Dois',
            'cnpj' => $this->cnpjValido('escola-dois'),
            'admin_nome' => 'Admin Dois',
            'admin_email' => $email,
            'admin_password' => $senha,
            'admin_password_confirmation' => $senha,
        ])->assertCreated();

        $this->assertSame(2, User::where('email', $email)->count());

        $instituicaoUm = Instituicao::where('slug', 'escola-um')->first();
        $instituicaoDois = Instituicao::where('slug', 'escola-dois')->first();
        $userUm = User::where('email', $email)->where('nome', 'Admin Um')->first();
        $userDois = User::where('email', $email)->where('nome', 'Admin Dois')->first();

        $this->assertNotNull($userUm);
        $this->assertNotNull($userDois);
        $this->assertNotSame($userUm->id, $userDois->id);

        $this->assertDatabaseHas('instituicoes_usuarios', [
            'id_instituicao' => $instituicaoUm->id,
            'id_usuario' => $userUm->id,
        ]);
        $this->assertDatabaseHas('instituicoes_usuarios', [
            'id_instituicao' => $instituicaoDois->id,
            'id_usuario' => $userDois->id,
        ]);
    }

    public function test_login_com_mesmo_email_e_senha_em_escolas_diferentes_usa_cnpj(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $email = 'gestor@multi-escola.com';
        $senha = 'Password1!';

        foreach (['multi-a' => 'Gestor A', 'multi-b' => 'Gestor B'] as $slug => $nome) {
            $this->postJson('/api/platform/instituicoes', [
                'slug' => $slug,
                'nome_fantasia' => strtoupper($slug),
                'cnpj' => $this->cnpjValido($slug),
                'admin_nome' => $nome,
                'admin_email' => $email,
                'admin_password' => $senha,
                'admin_password_confirmation' => $senha,
            ])->assertCreated();
        }

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => $senha,
            'tenant_cnpj' => $this->cnpjValido('multi-a'),
        ])
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'multi-a')
            ->assertJsonPath('data.nome', 'Gestor A');

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => $senha,
            'tenant_cnpj' => $this->cnpjValido('multi-b'),
        ])
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'multi-b')
            ->assertJsonPath('data.nome', 'Gestor B');
    }

    public function test_login_com_mesmo_email_em_escolas_diferentes_usa_tenant_slug(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $email = 'gestor-slug@multi-escola.com';
        $senha = 'Password1!';

        foreach (['slug-a' => 'Gestor A', 'slug-b' => 'Gestor B'] as $slug => $nome) {
            $this->postJson('/api/platform/instituicoes', [
                'slug' => $slug,
                'nome_fantasia' => strtoupper($slug),
                'cnpj' => $this->cnpjValido($slug),
                'admin_nome' => $nome,
                'admin_email' => $email,
                'admin_password' => $senha,
                'admin_password_confirmation' => $senha,
            ])->assertCreated();
        }

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => $senha,
            'tenant_slug' => 'slug-b',
        ])
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'slug-b')
            ->assertJsonPath('data.nome', 'Gestor B');
    }
}
