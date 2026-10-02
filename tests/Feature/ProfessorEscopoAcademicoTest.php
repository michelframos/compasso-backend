<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class ProfessorEscopoAcademicoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioProfessor;

    private User $usuarioAluno;

    private Turma $turma;

    private Turma $outraTurma;

    private AulaTurma $aula;

    private AulaTurma $aulaAlheia;

    private MaterialTurma $material;

    private MaterialTurma $materialAlheio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $this->usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $usuarioOutroProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuarioOutroAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');

        $this->naInstituicao($this->instituicao, function () use ($usuarioOutroProfessor, $usuarioOutroAluno): void {
            $curso = Curso::create(['nome' => 'Piano', 'descricao' => 'Teclas']);
            $nivel = Nivel::create(['nome' => 'Básico']);

            $professor = Professor::create(['id_usuario' => $this->usuarioProfessor->id]);
            $outroProfessor = Professor::create(['id_usuario' => $usuarioOutroProfessor->id]);

            $this->turma = $this->criarTurma($curso, $nivel, $professor);
            $this->outraTurma = $this->criarTurma($curso, $nivel, $outroProfessor);

            $this->aula = $this->criarAula($this->turma);
            $this->aulaAlheia = $this->criarAula($this->outraTurma);

            $this->material = MaterialTurma::create(['id_turma' => $this->turma->id, 'titulo' => 'Escalas', 'file_path' => 'a.pdf']);
            $this->materialAlheio = MaterialTurma::create(['id_turma' => $this->outraTurma->id, 'titulo' => 'Partitura', 'file_path' => 'b.pdf']);

            $aluno = Aluno::create(['id_usuario' => $this->usuarioAluno->id]);
            $outroAluno = Aluno::create(['id_usuario' => $usuarioOutroAluno->id]);

            foreach ([$aluno, $outroAluno] as $matriculado) {
                Matricula::create([
                    'id_aluno' => $matriculado->id,
                    'id_turma' => $this->turma->id,
                    'tipo' => 'turma',
                    'data' => now()->toDateString(),
                    'status' => 'ativa',
                ]);
            }
        });
    }

    private function criarTurma(Curso $curso, Nivel $nivel, Professor $professor): Turma
    {
        return Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::ABERTA,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => now()->toDateString(),
        ]);
    }

    private function criarAula(Turma $turma): AulaTurma
    {
        return AulaTurma::create([
            'id_turma' => $turma->id,
            'id_professor' => $turma->id_professor,
            'data' => now()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => 'agendada',
        ]);
    }

    public function test_professor_lista_somente_aulas_das_suas_turmas(): void
    {
        $ids = $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson('/api/aulas-turmas')
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$this->aula->id], $ids);

        $periodo = $this->getJson('/api/aulas-turmas?data_inicio='.now()->subDay()->toDateString())
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$this->aula->id], $periodo);
    }

    public function test_professor_nao_ve_aula_de_turma_alheia(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson("/api/aulas-turmas/{$this->aulaAlheia->id}")
            ->assertForbidden();

        $this->getJson("/api/aulas-turmas/{$this->aula->id}")->assertOk();
    }

    public function test_secretaria_lista_todas_as_aulas(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');

        $this->comoUsuario($secretaria, $this->instituicao)
            ->getJson('/api/aulas-turmas')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_professor_lista_somente_materiais_das_suas_turmas(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao);

        $this->assertSame(
            [$this->material->id],
            $this->getJson('/api/materiais-turmas')->assertOk()->json('data.*.id')
        );
        $this->getJson("/api/turmas/{$this->outraTurma->id}/materiais")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/materiais-turmas/{$this->materialAlheio->id}")->assertNotFound();
        $this->putJson("/api/materiais-turmas/{$this->materialAlheio->id}", ['titulo' => 'Alterado'])->assertNotFound();
    }

    public function test_aluno_ve_somente_a_propria_matricula_na_turma(): void
    {
        $this->comoUsuario($this->usuarioAluno, $this->instituicao)
            ->getJson("/api/turmas/{$this->turma->id}/matriculas")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_aluno', $this->usuarioAluno->aluno()->withoutGlobalScopes()->value('id'));
    }

    public function test_professor_nao_lista_matriculas_de_turma_alheia(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson("/api/turmas/{$this->outraTurma->id}/matriculas")
            ->assertForbidden();

        $this->getJson("/api/turmas/{$this->turma->id}/matriculas")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
