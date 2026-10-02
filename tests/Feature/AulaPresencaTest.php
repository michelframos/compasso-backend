<?php

namespace Tests\Feature;

use App\Models\AulaTurma;
use App\Models\AulaPresenca;
use App\Models\Turma;
use App\Models\Professor;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Nivel;
use App\Models\User;
use App\Enums\TurmaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AulaPresencaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Turma $turma;
    private AulaTurma $aula;
    private Aluno $aluno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $curso = Curso::create(['nome' => 'Curso Teste', 'status' => 'ativo']);
        $nivel = Nivel::create(['nome' => 'Nível Teste', 'status' => 'ativo']);
        $professor = Professor::factory()->create();

        $this->turma = Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'maximo_alunos' => 10,
            'descricao' => 'Turma Teste',
            'status' => TurmaStatus::EM_ANDAMENTO
        ]);

        $this->aula = AulaTurma::create([
            'id_turma' => $this->turma->id,
            'id_professor' => $professor->id,
            'data' => now()->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => 'agendada'
        ]);

        $userAluno = User::factory()->create();
        $this->aluno = Aluno::create([
            'id_usuario' => $userAluno->id,
            'observacoes' => 'Aluno Teste',
        ]);
    }

    public function test_can_list_presencas()
    {
        AulaPresenca::create([
            'id_aula_turma' => $this->aula->id,
            'id_aluno' => $this->aluno->id,
            'status' => 'presente'
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/aulas-turmas/{$this->aula->id}/presencas");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['status' => 'presente']);
    }

    public function test_can_sync_presencas()
    {
        $data = [
            'presencas' => [
                ['id_aluno' => $this->aluno->id, 'status' => 'presente']
            ]
        ];

        $response = $this->actingAs($this->user)->postJson("/api/aulas-turmas/{$this->aula->id}/presencas/sync", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('aulas_presencas', [
            'id_aula_turma' => $this->aula->id,
            'id_aluno' => $this->aluno->id,
            'status' => 'presente'
        ]);
    }

    public function test_cannot_sync_presencas_if_turma_concluida()
    {
        $this->turma->update(['status' => TurmaStatus::CONCLUIDA]);

        $data = [
            'presencas' => [
                ['id_aluno' => $this->aluno->id, 'status' => 'presente']
            ]
        ];

        $response = $this->actingAs($this->user)->postJson("/api/aulas-turmas/{$this->aula->id}/presencas/sync", $data);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Não é possível alterar presenças de turmas concluídas ou canceladas.']);
    }

    public function test_can_sync_presencas_if_aula_has_no_turma()
    {
        $this->aula->update(['id_turma' => null]);

        $data = [
            'presencas' => [
                ['id_aluno' => $this->aluno->id, 'status' => 'presente']
            ]
        ];

        $response = $this->actingAs($this->user)->postJson("/api/aulas-turmas/{$this->aula->id}/presencas/sync", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('aulas_presencas', [
            'id_aula_turma' => $this->aula->id,
            'id_aluno' => $this->aluno->id,
            'status' => 'presente'
        ]);
    }

    public function test_cannot_sync_presencas_if_aula_concluida()
    {
        $this->aula->update(['status' => 'concluida']);

        $data = [
            'presencas' => [
                ['id_aluno' => $this->aluno->id, 'status' => 'presente']
            ]
        ];

        $response = $this->actingAs($this->user)->postJson("/api/aulas-turmas/{$this->aula->id}/presencas/sync", $data);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Não é possível alterar presenças de turmas concluídas ou canceladas.']);
    }

    public function test_can_delete_individual_presenca()
    {
        $presenca = AulaPresenca::create([
            'id_aula_turma' => $this->aula->id,
            'id_aluno' => $this->aluno->id,
            'status' => 'presente'
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/presencas/{$presenca->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('aulas_presencas', ['id' => $presenca->id]);
    }

    public function test_can_delete_all_presencas_of_an_aula()
    {
        AulaPresenca::create([
            'id_aula_turma' => $this->aula->id,
            'id_aluno' => $this->aluno->id,
            'status' => 'presente'
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/aulas-turmas/{$this->aula->id}/presencas");

        $response->assertStatus(204);
        $this->assertEquals(0, AulaPresenca::where('id_aula_turma', $this->aula->id)->count());
    }

    public function test_cannot_delete_presenca_if_turma_concluida()
    {
        $presenca = AulaPresenca::create([
            'id_aula_turma' => $this->aula->id,
            'id_aluno' => $this->aluno->id,
            'status' => 'presente'
        ]);

        $this->turma->update(['status' => TurmaStatus::CONCLUIDA]);

        $response = $this->actingAs($this->user)->deleteJson("/api/presencas/{$presenca->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Não é possível alterar presenças de turmas concluídas ou canceladas.']);
    }
}
