<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\Responsavel;
use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class MatriculaAcessoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAluno;

    private User $usuarioOutroAluno;

    private User $usuarioResponsavel;

    private User $usuarioProfessor;

    private Matricula $matriculaAluno;

    private Matricula $matriculaOutroAluno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();

        $this->usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $this->usuarioOutroAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $this->usuarioResponsavel = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel');
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuarioOutroProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');

        $this->naInstituicao($this->instituicao, function () use ($usuarioOutroProfessor): void {
            $curso = Curso::create(['nome' => 'Violino', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);

            $professor = Professor::create(['id_usuario' => $this->usuarioProfessor->id, 'comissao' => 10]);
            $outroProfessor = Professor::create(['id_usuario' => $usuarioOutroProfessor->id, 'comissao' => 10]);

            $turma = $this->criarTurma($curso, $nivel, $professor);
            $outraTurma = $this->criarTurma($curso, $nivel, $outroProfessor);

            $aluno = Aluno::create(['id_usuario' => $this->usuarioAluno->id]);
            $outroAluno = Aluno::create(['id_usuario' => $this->usuarioOutroAluno->id]);

            $responsavel = Responsavel::create(['id_usuario' => $this->usuarioResponsavel->id]);
            $aluno->responsaveis()->attach($responsavel->id, ['parentesco' => 'Mãe']);

            $this->matriculaAluno = Matricula::create([
                'id_aluno' => $aluno->id,
                'id_turma' => $turma->id,
                'tipo' => 'turma',
                'data' => now()->toDateString(),
                'status' => 'ativa',
            ]);

            $this->matriculaOutroAluno = Matricula::create([
                'id_aluno' => $outroAluno->id,
                'id_turma' => $outraTurma->id,
                'tipo' => 'turma',
                'data' => now()->toDateString(),
                'status' => 'ativa',
            ]);
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

    private function idsListados(User $user): array
    {
        return collect(
            $this->comoUsuario($user, $this->instituicao)->getJson('/api/matriculas')->assertOk()->json('data')
        )->pluck('id')->all();
    }

    public function test_admin_lista_todas_as_matriculas(): void
    {
        $admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');

        $ids = $this->idsListados($admin);

        $this->assertEqualsCanonicalizing([$this->matriculaAluno->id, $this->matriculaOutroAluno->id], $ids);
    }

    public function test_aluno_lista_apenas_as_proprias_matriculas(): void
    {
        $this->assertSame([$this->matriculaAluno->id], $this->idsListados($this->usuarioAluno));
    }

    public function test_responsavel_lista_apenas_matriculas_dos_dependentes(): void
    {
        $this->assertSame([$this->matriculaAluno->id], $this->idsListados($this->usuarioResponsavel));
    }

    public function test_professor_lista_apenas_matriculas_das_suas_turmas(): void
    {
        $this->assertSame([$this->matriculaAluno->id], $this->idsListados($this->usuarioProfessor));
    }

    public function test_aluno_ve_a_propria_matricula_e_nao_a_de_outro(): void
    {
        $this->comoUsuario($this->usuarioAluno, $this->instituicao)
            ->getJson("/api/matriculas/{$this->matriculaAluno->id}")
            ->assertOk();

        $this->comoUsuario($this->usuarioAluno, $this->instituicao)
            ->getJson("/api/matriculas/{$this->matriculaOutroAluno->id}")
            ->assertForbidden();
    }

    public function test_responsavel_ve_matricula_do_dependente_e_nao_a_de_outro(): void
    {
        $this->comoUsuario($this->usuarioResponsavel, $this->instituicao)
            ->getJson("/api/matriculas/{$this->matriculaAluno->id}")
            ->assertOk();

        $this->comoUsuario($this->usuarioResponsavel, $this->instituicao)
            ->getJson("/api/matriculas/{$this->matriculaOutroAluno->id}")
            ->assertForbidden();
    }

    public function test_professor_ve_matricula_da_sua_turma_e_nao_de_outra(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson("/api/matriculas/{$this->matriculaAluno->id}")
            ->assertOk();

        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson("/api/matriculas/{$this->matriculaOutroAluno->id}")
            ->assertForbidden();
    }

    public function test_aluno_nao_ve_matriculas_de_outro_aluno_por_rota_de_aluno(): void
    {
        $idOutroAluno = $this->matriculaOutroAluno->id_aluno;

        $this->comoUsuario($this->usuarioAluno, $this->instituicao)
            ->getJson("/api/alunos/{$idOutroAluno}/matriculas")
            ->assertForbidden();
    }

    public function test_professor_nao_ve_matriculas_de_turma_alheia(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson("/api/turmas/{$this->matriculaOutroAluno->id_turma}/matriculas")
            ->assertForbidden();

        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson("/api/turmas/{$this->matriculaAluno->id_turma}/matriculas")
            ->assertOk();
    }

    public function test_aluno_nao_ve_mensalidades_de_matricula_alheia(): void
    {
        $this->comoUsuario($this->usuarioAluno, $this->instituicao)
            ->getJson("/api/matriculas/{$this->matriculaOutroAluno->id}/mensalidades")
            ->assertForbidden();
    }
}
