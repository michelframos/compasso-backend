<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformPlanoAssinaturaTest extends TestCase
{
    use RefreshDatabase;

    private function criarSuperAdmin(): User
    {
        return User::factory()->create([
            'email' => 'super-plano@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
    }

    private function criarUsuarioComum(): User
    {
        return User::factory()->create([
            'email' => 'comum-plano@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => false,
        ]);
    }

    public function test_usuario_comum_nao_acessa_platform_planos(): void
    {
        Sanctum::actingAs($this->criarUsuarioComum());

        $this->getJson('/api/platform/planos')
            ->assertForbidden()
            ->assertJsonPath('message', 'Acesso restrito à plataforma.');
    }

    public function test_super_admin_lista_planos(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        PlanoAssinatura::create([
            'slug' => 'starter',
            'nome' => 'Starter',
            'preco_mensal' => 49.90,
            'ativo' => true,
        ]);

        $response = $this->getJson('/api/platform/planos');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'nome', 'preco_mensal', 'ativo']]]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_super_admin_filtra_planos_ativos(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        PlanoAssinatura::create([
            'slug' => 'ativo',
            'nome' => 'Ativo',
            'preco_mensal' => 10,
            'ativo' => true,
        ]);
        PlanoAssinatura::create([
            'slug' => 'inativo',
            'nome' => 'Inativo',
            'preco_mensal' => 10,
            'ativo' => false,
        ]);

        $response = $this->getJson('/api/platform/planos?ativo=1');

        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains('ativo'));
        $this->assertFalse($slugs->contains('inativo'));
    }

    public function test_super_admin_cria_plano(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $response = $this->postJson('/api/platform/planos', [
            'slug' => 'novo-plano',
            'nome' => 'Novo Plano',
            'descricao' => 'Descrição do plano',
            'preco_mensal' => 149.90,
            'limite_alunos' => 120,
            'modulos' => ['leads', 'financeiro'],
            'ativo' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'novo-plano')
            ->assertJsonPath('data.nome', 'Novo Plano')
            ->assertJsonPath('data.ativo', true)
            ->assertJsonPath('data.modulos', ['leads', 'financeiro']);

        $this->assertDatabaseHas('planos_assinatura', [
            'slug' => 'novo-plano',
            'nome' => 'Novo Plano',
        ]);
    }

    public function test_super_admin_exibe_plano(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $plano = PlanoAssinatura::create([
            'slug' => 'detalhe',
            'nome' => 'Detalhe',
            'preco_mensal' => 79.90,
            'ativo' => true,
        ]);

        $this->getJson("/api/platform/planos/{$plano->id}")
            ->assertOk()
            ->assertJsonPath('data.slug', 'detalhe');
    }

    public function test_super_admin_atualiza_plano(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $plano = PlanoAssinatura::create([
            'slug' => 'editar',
            'nome' => 'Editar',
            'preco_mensal' => 50,
            'ativo' => true,
        ]);

        $response = $this->putJson("/api/platform/planos/{$plano->id}", [
            'nome' => 'Editado',
            'preco_mensal' => 89.90,
            'ativo' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.nome', 'Editado')
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('planos_assinatura', [
            'id' => $plano->id,
            'nome' => 'Editado',
            'ativo' => false,
        ]);
    }

    public function test_super_admin_exclui_plano_sem_escolas_vinculadas(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $plano = PlanoAssinatura::create([
            'slug' => 'remover',
            'nome' => 'Remover',
            'preco_mensal' => 10,
            'ativo' => true,
        ]);

        $this->deleteJson("/api/platform/planos/{$plano->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('planos_assinatura', ['id' => $plano->id]);
    }

    public function test_super_admin_nao_exclui_plano_com_escolas_vinculadas(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $plano = PlanoAssinatura::create([
            'slug' => 'vinculado',
            'nome' => 'Vinculado',
            'preco_mensal' => 10,
            'ativo' => true,
        ]);

        Instituicao::create([
            'slug' => 'escola-com-plano',
            'nome_fantasia' => 'Escola com Plano',
            'status' => Instituicao::STATUS_ATIVO,
            'id_plano_assinatura' => $plano->id,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
        ]);

        $this->deleteJson("/api/platform/planos/{$plano->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plano']);

        $this->assertDatabaseHas('planos_assinatura', [
            'id' => $plano->id,
            'deleted_at' => null,
        ]);
    }

    public function test_super_admin_lista_planos_com_with_trashed(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $plano = PlanoAssinatura::create([
            'slug' => 'trashed',
            'nome' => 'Trashed',
            'preco_mensal' => 10,
            'ativo' => true,
        ]);
        $plano->delete();

        $response = $this->getJson('/api/platform/planos?with_trashed=1');

        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains('trashed'));
    }

    public function test_plano_assinatura_seeder_cria_planos_exemplo(): void
    {
        $this->seed(\Database\Seeders\PlanoAssinaturaSeeder::class);

        $this->assertDatabaseHas('planos_assinatura', ['slug' => 'basico']);
        $this->assertDatabaseHas('planos_assinatura', ['slug' => 'profissional']);
        $this->assertDatabaseHas('planos_assinatura', ['slug' => 'enterprise']);
    }
}
