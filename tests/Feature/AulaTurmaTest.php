<?php

namespace Tests\Feature;

use App\Models\AulaTurma;
use App\Models\Turma;
use App\Models\Professor;
use App\Models\Curso;
use App\Models\Nivel;
use App\Models\User;
use App\Enums\TurmaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AulaTurmaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Turma $turma;
    private Professor $professor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $curso = Curso::create([
            'nome' => 'Curso Teste',
            'descricao' => 'Descrição do curso',
            'status' => 'ativo'
        ]);

        $nivel = Nivel::create([
            'nome' => 'Nível Teste',
            'descricao' => 'Descrição do nível',
            'status' => 'ativo'
        ]);

        $professorUser = User::factory()->create();

        $this->professor = Professor::create([
            'id_usuario' => $professorUser->id,
            'nome' => 'Professor Teste',
            'email' => 'professor.teste@email.com',
            'telefone' => '11999999999',
            'formacao' => 'Mestrado em Música',
            'instrumento_principal' => 'Violino',
            'status' => 'ativo'
        ]);

        $this->turma = Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $this->professor->id,
            'maximo_alunos' => 10,
            'descricao' => 'Turma Teste',
            'status' => TurmaStatus::EM_ANDAMENTO
        ]);
    }

    public function test_can_list_aulas_turmas()
    {
        AulaTurma::create([
            'id_turma' => $this->turma->id,
            'id_professor' => $this->professor->id,
            'data' => '2023-11-01',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => 'agendada'
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/aulas-turmas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'id_turma', 'id_professor', 'data', 'status']
                ]
            ]);
    }

    public function test_can_create_aula_turma()
    {
        $data = [
            'id_turma' => $this->turma->id,
            'id_professor' => $this->professor->id,
            'data' => '2023-11-05',
            'hora_inicio' => '14:00',
            'hora_termino' => '15:00',
            'status' => 'agendada',
            'conteudo_dado' => 'Teste'
        ];

        $response = $this->actingAs($this->user)->postJson('/api/aulas-turmas', $data);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'agendada']);

        $this->assertDatabaseHas('aulas_turmas', ['data' => '2023-11-05']);
    }

    public function test_cannot_update_concluida_aula()
    {
        $aula = AulaTurma::create([
            'id_turma' => $this->turma->id,
            'id_professor' => $this->professor->id,
            'data' => '2023-11-01',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => 'concluida'
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/aulas-turmas/{$aula->id}", [
            'conteudo_dado' => 'Alterado'
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('aulas_turmas', ['conteudo_dado' => 'Alterado']);
    }

    public function test_cannot_delete_concluida_aula()
    {
        $aula = AulaTurma::create([
            'id_turma' => $this->turma->id,
            'id_professor' => $this->professor->id,
            'data' => '2023-11-01',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => 'concluida'
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/aulas-turmas/{$aula->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('aulas_turmas', ['id' => $aula->id]);
    }

    public function test_cannot_update_aula_of_concluida_turma()
    {
        $this->turma->update(['status' => TurmaStatus::CONCLUIDA]);

        $aula = AulaTurma::create([
            'id_turma' => $this->turma->id,
            'id_professor' => $this->professor->id,
            'data' => '2023-11-01',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => 'agendada'
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/aulas-turmas/{$aula->id}", [
            'status' => 'concluida'
        ]);

        $response->assertStatus(403);
    }

    public function test_can_soft_delete_aula()
    {
        $aula = AulaTurma::create([
            'id_turma' => $this->turma->id,
            'id_professor' => $this->professor->id,
            'data' => '2023-11-01',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => 'agendada'
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/aulas-turmas/{$aula->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('aulas_turmas', ['id' => $aula->id]);
    }

    public function test_captures_financial_snapshots_when_concluida()
    {
        // 1. Setup specific values
        $this->professor->update([
            'comissao' => 15.00,
            'valor_hora_aula' => 50.00
        ]);

        $this->turma->update([
            'valor_mensalidade' => 300.00,
            'percentual_comissao_especifico' => 25.00,
            'valor_hora_aula_especifico' => 70.00
        ]);

        // 2. Create a concluded aula
        $data = [
            'id_turma' => $this->turma->id,
            'id_professor' => $this->professor->id,
            'data' => '2023-11-10',
            'hora_inicio' => '14:00',
            'hora_termino' => '15:00',
            'status' => 'concluida',
            'conteudo_dado' => 'Aula com Snapshot'
        ];

        $response = $this->actingAs($this->user)->postJson('/api/aulas-turmas', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('aulas_turmas', [
            'data' => '2023-11-10',
            'valor_hora_aula_aplicado' => 70.00,
            'percentual_comissao_aplicado' => 25.00,
            'valor_mensalidade_aplicado' => 300.00
        ]);

        // 3. Test update to concluida (fallback to professor values)
        $this->turma->update([
            'percentual_comissao_especifico' => null,
            'valor_hora_aula_especifico' => null
        ]);

        $aula = AulaTurma::create([
            'id_turma' => $this->turma->id,
            'id_professor' => $this->professor->id,
            'data' => '2023-11-11',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => 'agendada'
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/aulas-turmas/{$aula->id}", [
            'status' => 'concluida'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('aulas_turmas', [
            'id' => $aula->id,
            'status' => 'concluida',
            'valor_hora_aula_aplicado' => 50.00,
            'percentual_comissao_aplicado' => 15.00,
            'valor_mensalidade_aplicado' => 300.00
        ]);
    }
}
