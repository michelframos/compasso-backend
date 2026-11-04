<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AvaliacaoAluno;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\ObservacaoAluno;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoBoletimTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $bruno;

    private Matricula $matriculaAna;

    private Matricula $matriculaBruno;

    private Turma $turmaAna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno, $usuarioPaula): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $paula = Professor::create(['id_usuario' => $usuarioPaula->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $this->turmaAna = Turma::create([
                'id_curso' => $curso->id,
                'id_nivel' => $nivel->id,
                'id_professor' => $paula->id,
                'descricao' => 'Turma Ana',
                'maximo_alunos' => 10,
                'status' => TurmaStatus::EM_ANDAMENTO,
                'tipo_agendamento' => 'quantidade',
                'quantidade_aulas' => 10,
                'data_inicio' => '2026-09-01',
                'valor_mensalidade' => 350,
            ]);
            $turmaBruno = Turma::create([
                'id_curso' => $curso->id,
                'id_nivel' => $nivel->id,
                'id_professor' => $paula->id,
                'descricao' => 'Turma Bruno',
                'maximo_alunos' => 10,
                'status' => TurmaStatus::EM_ANDAMENTO,
                'tipo_agendamento' => 'quantidade',
                'quantidade_aulas' => 10,
                'data_inicio' => '2026-09-01',
                'valor_mensalidade' => 350,
            ]);

            $this->matriculaAna = Matricula::create([
                'id_aluno' => $this->ana->id,
                'id_turma' => $this->turmaAna->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'ativa',
            ]);
            $this->matriculaBruno = Matricula::create([
                'id_aluno' => $this->bruno->id,
                'id_turma' => $turmaBruno->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'ativa',
            ]);

            AvaliacaoAluno::create([
                'id_aluno' => $this->ana->id,
                'id_turma' => $this->turmaAna->id,
                'id_professor' => $paula->id,
                'data' => '2026-10-10',
                'tipo' => 'pratica',
                'nota' => 8.5,
                'conceito' => 'bom',
                'comentario' => 'Boa evolução no ritmo.',
            ]);
            AvaliacaoAluno::create([
                'id_aluno' => $this->bruno->id,
                'id_turma' => $turmaBruno->id,
                'id_professor' => $paula->id,
                'data' => '2026-10-11',
                'tipo' => 'teorica',
                'conceito' => 'excelente',
                'comentario' => 'Segredo do Bruno.',
            ]);

            ObservacaoAluno::create([
                'id_aluno' => $this->ana->id,
                'id_professor' => $paula->id,
                'id_turma' => $this->turmaAna->id,
                'tipo' => 'evolucao',
                'texto' => 'Melhorou a postura.',
                'visivel_responsavel' => true,
            ]);
            ObservacaoAluno::create([
                'id_aluno' => $this->ana->id,
                'id_professor' => $paula->id,
                'id_turma' => $this->turmaAna->id,
                'tipo' => 'alerta',
                'texto' => 'Nota interna da Paula.',
                'visivel_responsavel' => false,
            ]);
            ObservacaoAluno::create([
                'id_aluno' => $this->bruno->id,
                'id_professor' => $paula->id,
                'id_turma' => $turmaBruno->id,
                'tipo' => 'geral',
                'texto' => 'Feedback do Bruno.',
                'visivel_responsavel' => true,
            ]);
        });
    }

    public function test_aluno_ve_nivel_avaliacoes_e_so_feedback_liberado(): void
    {
        $boletim = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/boletim')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $boletim['matriculas']);
        $grupo = $boletim['matriculas'][0];
        $this->assertSame($this->matriculaAna->id, $grupo['id_matricula']);
        $this->assertSame('Básico', $grupo['nivel']['nome']);
        $this->assertCount(1, $grupo['avaliacoes']);
        $this->assertSame(8.5, $grupo['avaliacoes'][0]['nota']);
        $this->assertSame('Boa evolução no ritmo.', $grupo['avaliacoes'][0]['comentario']);
        $this->assertSame('Paula Prof', $grupo['avaliacoes'][0]['professor']);
        $this->assertCount(1, $grupo['observacoes']);
        $this->assertSame('Melhorou a postura.', $grupo['observacoes'][0]['texto']);

        $corpo = json_encode($boletim);
        $this->assertStringNotContainsString('Nota interna da Paula.', $corpo);
        $this->assertStringNotContainsString('Segredo do Bruno.', $corpo);
        $this->assertStringNotContainsString('Feedback do Bruno.', $corpo);
        $this->assertStringNotContainsString('Bruno Melo', $corpo);
        $this->assertStringNotContainsString('visivel_responsavel', $corpo);
        $this->assertStringNotContainsString('pode_editar', $corpo);

        $filtrado = $this->getJson('/api/aluno/me/boletim?id_matricula='.$this->matriculaAna->id)
            ->assertOk()
            ->json('data.matriculas');
        $this->assertCount(1, $filtrado);
        $this->assertSame($this->matriculaAna->id, $filtrado[0]['id_matricula']);

        $this->getJson('/api/aluno/me/boletim?id_matricula='.$this->matriculaBruno->id)->assertForbidden();

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/boletim')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $daRita = $this->getJson('/api/aluno/me/boletim?id_aluno='.$this->ana->id)
            ->assertOk()
            ->json('data.matriculas.0');
        $this->assertSame('Melhorou a postura.', $daRita['observacoes'][0]['texto']);
        $this->assertCount(1, $daRita['avaliacoes']);

        $this->getJson('/api/aluno/me/boletim?id_aluno='.$this->bruno->id)->assertForbidden();
    }

    public function test_demais_papeis_e_sessao_recebem_401_ou_403(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/boletim')
            ->assertUnauthorized();

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/boletim')
                ->assertForbidden();
        }

        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'aluno', ['nome' => 'Aluno de Fora']);
        $this->naInstituicao($outra, fn () => Aluno::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/aluno/me/boletim')
            ->assertForbidden();
    }
}
