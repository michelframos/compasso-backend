<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Aluno;
use App\Models\MedidaAluno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MedidaAlunoTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $aluno;

    protected function setUp(): void
    {
        parent::setUp();
        // Create user and authenticate
        $this->user = User::factory()->create();

        // Create Aluno manually as there is no factory
        $this->aluno = Aluno::create([
            'id_usuario' => User::factory()->create()->id,
            // Add other required fields if any based on the model, but for now assuming mostly nullable or defaults
            'nome' => 'Aluno Teste',
        ]);
    }

    public function test_can_list_medidas_alunos()
    {
        MedidaAluno::create([
            'id_aluno' => $this->aluno->id,
            'medida_torax' => 90.0,
            'medida_cintura' => 70.0,
            'medida_quadril' => 100.0,
            'medida_altura' => 170.0
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/medidas-alunos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'id_aluno',
                    'medida_torax',
                    'medida_cintura',
                    'medida_quadril',
                    'medida_altura',
                    'ativo',
                    'criado_em',
                ]
            ]);
    }

    public function test_can_create_medida_aluno()
    {
        $data = [
            'id_aluno' => $this->aluno->id,
            'medida_torax' => 90.5,
            'medida_cintura' => 75.0,
            'medida_quadril' => 100.0,
            'medida_altura' => 170.0,
            'ativo' => true,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/medidas-alunos', $data);

        $response->assertStatus(201)
            ->assertJsonFragment($data);

        $this->assertDatabaseHas('medidas_alunos', [
            'id_aluno' => $this->aluno->id,
            'medida_torax' => 90.5
        ]);
    }

    public function test_can_show_medida_aluno()
    {
        $medida = MedidaAluno::create([
            'id_aluno' => $this->aluno->id,
            'medida_torax' => 88.0,
            'medida_cintura' => 68.0,
            'medida_quadril' => 98.0,
            'medida_altura' => 168.0
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/medidas-alunos/{$medida->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $medida->id,
                'id_aluno' => $this->aluno->id,
            ]);
    }

    public function test_can_update_medida_aluno()
    {
        $medida = MedidaAluno::create([
            'id_aluno' => $this->aluno->id,
            'medida_torax' => 88.0,
            'medida_cintura' => 68.0,
            'medida_quadril' => 98.0,
            'medida_altura' => 168.0
        ]);

        $data = [
            'medida_torax' => 95.0,
            'medida_cintura' => 80.0,
        ];

        $response = $this->actingAs($this->user)->putJson("/api/medidas-alunos/{$medida->id}", $data);

        $response->assertStatus(200)
            ->assertJsonFragment($data);

        $this->assertDatabaseHas('medidas_alunos', array_merge(['id' => $medida->id], $data));
    }

    public function test_can_delete_medida_aluno()
    {
        $medida = MedidaAluno::create([
            'id_aluno' => $this->aluno->id,
            'medida_torax' => 88.0,
            'medida_cintura' => 68.0,
            'medida_quadril' => 98.0,
            'medida_altura' => 168.0
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/medidas-alunos/{$medida->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('medidas_alunos', ['id' => $medida->id]);
    }

    public function test_can_list_medidas_by_aluno()
    {
        MedidaAluno::create([
            'id_aluno' => $this->aluno->id,
            'medida_torax' => 90.0,
            'medida_cintura' => 70.0,
            'medida_quadril' => 100.0,
            'medida_altura' => 170.0
        ]);

        MedidaAluno::create([
            'id_aluno' => $this->aluno->id,
            'medida_torax' => 92.0,
            'medida_cintura' => 72.0,
            'medida_quadril' => 102.0,
            'medida_altura' => 172.0
        ]);

        // Create a measurement for another student to ensure it's not listed
        $otherAluno = Aluno::create([
             'id_usuario' => User::factory()->create()->id,
             'nome' => 'Outro Aluno',
        ]);
        MedidaAluno::create([
            'id_aluno' => $otherAluno->id,
            'medida_torax' => 80.0,
            'medida_cintura' => 60.0,
            'medida_quadril' => 90.0,
            'medida_altura' => 160.0
        ]);


        $response = $this->actingAs($this->user)->getJson("/api/alunos/{$this->aluno->id}/medidas");

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'id_aluno',
                    'medida_torax',
                    'medida_cintura',
                    'medida_quadril',
                    'medida_altura',
                    'ativo',
                    'criado_em',
                ]
            ]);
    }
}
