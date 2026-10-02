<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\CategoriaConta;

class CategoriaContaTest extends TestCase
{
    use DatabaseTransactions;

    protected $adminUser;
    protected $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        // Autenticar usuário admin
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->adminUser);

        // Criar uma categoria para uso nos testes
        $this->categoria = CategoriaConta::factory()->create([
            'nome' => 'Internet e Telefonia',
            'tipo' => 'despesa',
            'descricao' => 'Contas mensais de comunicação',
        ]);
    }

    public function test_can_list_categorias_contas()
    {
        $response = $this->getJson('/api/categorias-contas');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'nome', 'tipo', 'descricao']
                     ],
                     'links',
                     'meta'
                 ]);
    }

    public function test_can_create_categoria_conta()
    {
        $response = $this->postJson('/api/categorias-contas', [
            'nome'      => 'Venda de Ingressos',
            'tipo'      => 'receita',
            'descricao' => 'Arrecadação de eventos',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.nome', 'Venda de Ingressos')
                 ->assertJsonPath('data.tipo', 'receita');

        $this->assertDatabaseHas('categorias_contas', [
            'nome' => 'Venda de Ingressos',
            'tipo' => 'receita',
        ]);
    }

    public function test_cannot_create_categoria_conta_with_invalid_type()
    {
        $response = $this->postJson('/api/categorias-contas', [
            'nome'      => 'Invalido',
            'tipo'      => 'outro_tipo',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['tipo']);
    }

    public function test_can_show_categoria_conta()
    {
        $response = $this->getJson("/api/categorias-contas/{$this->categoria->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $this->categoria->id)
                 ->assertJsonPath('data.nome', 'Internet e Telefonia');
    }

    public function test_can_update_categoria_conta()
    {
        $response = $this->putJson("/api/categorias-contas/{$this->categoria->id}", [
            'nome' => 'Comunicação',
            'tipo' => 'despesa',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.nome', 'Comunicação');

        $this->assertDatabaseHas('categorias_contas', [
            'id'   => $this->categoria->id,
            'nome' => 'Comunicação',
        ]);
    }

    public function test_can_delete_categoria_conta()
    {
        $response = $this->deleteJson("/api/categorias-contas/{$this->categoria->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('categorias_contas', [
            'id' => $this->categoria->id,
        ]);
    }
}
