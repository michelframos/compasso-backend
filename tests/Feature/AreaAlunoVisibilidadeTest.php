<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\ApresentacaoAluno;
use App\Modules\Espetaculos\Models\Espetaculo;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoVisibilidadeTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioBruno;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $bruno;

    private Responsavel $rita;

    private AulaTurma $aula;

    private Apresentacao $daAna;

    private Apresentacao $doBruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $this->usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioProfessor): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $professor = Professor::create(['id_usuario' => $usuarioProfessor->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $this->usuarioBruno->id]);
            $this->rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($this->rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $turmaAna = $this->turma($curso, $nivel, $professor, 'Turma Ana');
            $turmaBruno = $this->turma($curso, $nivel, $professor, 'Turma Bruno');
            $this->matricular($this->ana, $turmaAna);
            $this->matricular($this->bruno, $turmaBruno);

            $this->aula = AulaTurma::create([
                'id_turma' => $turmaAna->id,
                'id_curso' => $curso->id,
                'id_professor' => $professor->id,
                'data' => '2026-09-10',
                'hora_inicio' => '14:00',
                'hora_termino' => '15:00',
                'status' => 'concluida',
                'tipo' => 'regular',
            ]);
            AulaPresenca::create(['id_aula_turma' => $this->aula->id, 'id_aluno' => $this->ana->id, 'status' => 'presente']);
            AulaPresenca::create(['id_aula_turma' => $this->aula->id, 'id_aluno' => $this->bruno->id, 'status' => 'ausente']);

            MaterialTurma::create(['id_turma' => $turmaAna->id, 'titulo' => 'Partitura da Ana', 'link' => 'https://exemplo.test/ana', 'publico' => true]);
            MaterialTurma::create(['id_turma' => $turmaAna->id, 'titulo' => 'Anotação interna', 'link' => 'https://exemplo.test/interno', 'publico' => false]);
            MaterialTurma::create(['id_turma' => $turmaBruno->id, 'titulo' => 'Partitura do Bruno', 'link' => 'https://exemplo.test/bruno', 'publico' => true]);

            $categoria = CategoriaConta::firstOrCreate(['nome' => 'Mensalidades'], ['tipo' => 'receita']);
            Conta::create([
                'id_categoria' => $categoria->id,
                'id_aluno' => $this->ana->id,
                'descricao' => 'Mensalidade da Ana',
                'valor' => 100,
                'data_vencimento' => '2026-09-10',
                'status' => 'pendente',
                'tipo' => 'receita',
            ]);
            Conta::create([
                'id_categoria' => $categoria->id,
                'id_aluno' => $this->bruno->id,
                'descricao' => 'Mensalidade do Bruno',
                'valor' => 100,
                'data_vencimento' => '2026-09-10',
                'status' => 'pendente',
                'tipo' => 'receita',
            ]);

            $espetaculo = Espetaculo::create(['titulo' => 'Recital', 'data_evento' => '2026-11-20', 'status' => 'planejamento']);
            $this->daAna = Apresentacao::create(['id_espetaculo' => $espetaculo->id, 'id_turma' => $turmaAna->id, 'titulo_musica' => 'Peça da Ana']);
            $this->doBruno = Apresentacao::create(['id_espetaculo' => $espetaculo->id, 'id_turma' => $turmaBruno->id, 'titulo_musica' => 'Peça do Bruno']);
            ApresentacaoAluno::create(['id_apresentacao' => $this->daAna->id, 'id_aluno' => $this->ana->id]);
            ApresentacaoAluno::create(['id_apresentacao' => $this->doBruno->id, 'id_aluno' => $this->bruno->id]);
        });
    }

    public function test_aluno_nao_ve_dados_de_outros(): void
    {
        $this->comoUsuario($this->usuarioAna, $this->instituicao);

        $this->getJson("/api/aulas-turmas/{$this->aula->id}/presencas")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_aluno', $this->ana->id);

        $materiais = $this->getJson('/api/materiais-turmas')->assertOk()->json('data');
        $this->assertSame(['Partitura da Ana'], collect($materiais)->pluck('titulo')->all());

        $contas = $this->getJson('/api/contas')->assertOk()->json('data');
        $this->assertSame(['Mensalidade da Ana'], collect($contas)->pluck('descricao')->all());

        $apresentacoes = $this->getJson('/api/apresentacoes')->assertOk()->json('data');
        $this->assertSame(['Peça da Ana'], collect($apresentacoes)->pluck('titulo_musica')->all());

        $participacoes = $this->getJson('/api/apresentacoes-alunos')->assertOk()->json('data');
        $this->assertSame([$this->ana->id], collect($participacoes)->pluck('id_aluno')->all());

        $this->getJson('/api/apresentacoes/'.$this->doBruno->id)->assertForbidden();
        $this->getJson('/api/apresentacoes/'.$this->daAna->id)->assertOk();
    }

    public function test_responsavel_so_ve_os_dependentes_e_perde_o_acesso_ao_desvincular(): void
    {
        $this->comoUsuario($this->usuarioRita, $this->instituicao);

        $this->getJson("/api/aulas-turmas/{$this->aula->id}/presencas")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_aluno', $this->ana->id);

        $this->assertSame(
            ['Partitura da Ana'],
            collect($this->getJson('/api/materiais-turmas')->assertOk()->json('data'))->pluck('titulo')->all()
        );
        $this->assertSame(
            ['Mensalidade da Ana'],
            collect($this->getJson('/api/contas')->assertOk()->json('data'))->pluck('descricao')->all()
        );
        $this->assertSame(
            ['Peça da Ana'],
            collect($this->getJson('/api/apresentacoes')->assertOk()->json('data'))->pluck('titulo_musica')->all()
        );
        $this->getJson('/api/apresentacoes/'.$this->doBruno->id)->assertForbidden();

        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $this->comoUsuario($secretaria, $this->instituicao)
            ->deleteJson("/api/alunos/{$this->ana->id}/responsaveis/{$this->rita->id}")
            ->assertOk();

        $this->comoUsuario($this->usuarioRita, $this->instituicao);
        $this->getJson('/api/aluno/me?id_aluno='.$this->ana->id)->assertForbidden();
        $this->getJson('/api/contas')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/apresentacoes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/aulas-turmas/{$this->aula->id}/presencas")->assertOk()->assertJsonCount(0, 'data');
    }

    private function turma(Curso $curso, Nivel $nivel, Professor $professor, string $descricao): Turma
    {
        return Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'descricao' => $descricao,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
        ]);
    }

    private function matricular(Aluno $aluno, Turma $turma): void
    {
        Matricula::create([
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
            'tipo' => 'turma',
            'data' => '2026-09-01',
            'status' => 'ativa',
        ]);
    }
}
