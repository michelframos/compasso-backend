<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class MatriculaRematriculaAposExclusaoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $admin;

    private Aluno $aluno;

    private Turma $turma;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');

        $this->naInstituicao($this->instituicao, function () use ($usuarioAluno, $usuarioProfessor): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Baby']);
            $professor = Professor::create(['id_usuario' => $usuarioProfessor->id, 'comissao' => 10]);
            $this->turma = Turma::create([
                'id_curso' => $curso->id,
                'id_nivel' => $nivel->id,
                'id_professor' => $professor->id,
                'maximo_alunos' => 10,
                'status' => TurmaStatus::ABERTA,
                'tipo_agendamento' => 'quantidade',
                'quantidade_aulas' => 10,
                'data_inicio' => now()->toDateString(),
            ]);
            $this->aluno = Aluno::create(['id_usuario' => $usuarioAluno->id]);
            $this->matricula = Matricula::create([
                'id_aluno' => $this->aluno->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'turma',
                'data' => now()->toDateString(),
                'status' => 'ativa',
            ]);
        });
    }

    public function test_pode_rematricular_apos_excluir_soft_delete(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->deleteJson("/api/matriculas/{$this->matricula->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('matriculas', ['id' => $this->matricula->id]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => now()->toDateString(),
                'status' => 'ativa',
            ])
            ->assertCreated()
            ->assertJsonPath('id', $this->matricula->id)
            ->assertJsonPath('id_aluno', $this->aluno->id)
            ->assertJsonPath('id_turma', $this->turma->id);

        $this->assertNull(Matricula::withTrashed()->findOrFail($this->matricula->id)->deleted_at);
        $this->assertSame(1, Matricula::query()
            ->where('id_aluno', $this->aluno->id)
            ->where('id_turma', $this->turma->id)
            ->count());
    }
}
