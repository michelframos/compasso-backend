<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Professor;

class ProfessorTest extends TestCase
{
    use DatabaseTransactions;

    protected $adminUser;
    protected $professor;
    protected $professorUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Autenticar usuário admin
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->adminUser);

        // Criar um professor para uso nos testes
        $this->professorUser = User::factory()->create(['role' => 'professor']);
        $this->professor = Professor::create([
            'id_usuario'  => $this->professorUser->id,
            'comissao'    => 10.00,
            'observacoes' => 'Professor de violino',
        ]);
    }

    public function test_can_list_professores()
    {
        $response = $this->getJson('/api/professores');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => [['id', 'id_usuario', 'comissao', 'observacoes']]]);
    }

    public function test_can_create_professor()
    {
        $response = $this->postJson('/api/professores', [
            'nome'                  => 'Ana Lima',
            'email'                 => 'ana.lima.' . uniqid() . '@email.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'cpf'                   => '529.982.247-25',
            'telefone'              => '(11) 91111-1111',
            'comissao'              => 20.00,
            'observacoes'           => 'Professora de piano',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('usuario.role', 'professor');
    }

    public function test_can_show_professor()
    {
        $response = $this->getJson("/api/professores/{$this->professor->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('id', $this->professor->id);
    }

    public function test_can_update_professor()
    {
        $response = $this->putJson("/api/professores/{$this->professor->id}", [
            'comissao'    => 25.00,
            'observacoes' => 'Atualizado',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('observacoes', 'Atualizado');
    }

    public function test_can_delete_professor()
    {
        $response = $this->deleteJson("/api/professores/{$this->professor->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('professores', ['id' => $this->professor->id]);
        $this->assertSoftDeleted('usuarios', ['id' => $this->professorUser->id]);
    }
}
