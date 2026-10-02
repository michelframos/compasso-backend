<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Aluno;
use App\Models\Responsavel;

class ResponsavelAlunoTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;
    protected $aluno;
    protected $responsavel;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user and authenticate
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->user);

        // Create Aluno and Responsavel
        $this->aluno = Aluno::create(['id_usuario' => User::factory()->create()->id]);
        $this->responsavel = Responsavel::create(['id_usuario' => User::factory()->create()->id]);
    }

    public function test_can_list_responsaveis_for_aluno()
    {
        $this->aluno->responsaveis()->attach($this->responsavel->id, ['parentesco' => 'Pai']);

        $response = $this->getJson("/api/alunos/{$this->aluno->id}/responsaveis");

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    public function test_can_list_alunos_for_responsavel()
    {
        $this->aluno->responsaveis()->attach($this->responsavel->id, ['parentesco' => 'Mãe']);

        $response = $this->getJson("/api/responsaveis/{$this->responsavel->id}/alunos");

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    public function test_can_attach_responsavel_to_aluno()
    {
        $response = $this->postJson("/api/alunos/{$this->aluno->id}/responsaveis", [
            'id_responsavel' => $this->responsavel->id,
            'parentesco' => 'Mãe',
            'observacoes' => 'Teste observação'
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.pivot.parentesco', 'Mãe');

        $this->assertDatabaseHas('responsaveis_alunos', [
            'id_aluno' => $this->aluno->id,
            'id_responsavel' => $this->responsavel->id,
            'parentesco' => 'Mãe'
        ]);
    }

    public function test_soft_delete_responsavel_from_aluno()
    {
        // Attach first
        $this->aluno->responsaveis()->attach($this->responsavel->id);

        $response = $this->deleteJson("/api/alunos/{$this->aluno->id}/responsaveis/{$this->responsavel->id}");

        $response->assertStatus(200);

        // Assert record still exists but deleted_at is not null
        $this->assertDatabaseHas('responsaveis_alunos', [
            'id_aluno' => $this->aluno->id,
            'id_responsavel' => $this->responsavel->id
        ]);

        $this->assertNotNull(
            \DB::table('responsaveis_alunos')
                ->where('id_aluno', $this->aluno->id)
                ->where('id_responsavel', $this->responsavel->id)
                ->value('deleted_at')
        );
    }
}
