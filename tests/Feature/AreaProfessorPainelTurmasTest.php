<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Models\TurmaHorario;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaProfessorPainelTurmasTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioProfessor;

    private Turma $turma;

    private Turma $turmaConcluida;

    private Turma $turmaAlheia;

    /** @var array<string, AulaTurma> */
    private array $aulas = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Quarta-feira: a semana vai de 28/09 (segunda) a 04/10 (domingo).
        $this->travelTo('2026-09-30 15:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuarioOutroProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuariosAlunos = [
            $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno'),
            $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno'),
            $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno'),
        ];

        $this->naInstituicao($this->instituicao, function () use ($usuarioOutroProfessor, $usuariosAlunos): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);

            $professor = Professor::create(['id_usuario' => $this->usuarioProfessor->id]);
            $outroProfessor = Professor::create(['id_usuario' => $usuarioOutroProfessor->id]);

            $this->turma = $this->criarTurma($curso, $nivel, $professor, TurmaStatus::ABERTA);
            $this->turmaConcluida = $this->criarTurma($curso, $nivel, $professor, TurmaStatus::CONCLUIDA);
            $this->turmaAlheia = $this->criarTurma($curso, $nivel, $outroProfessor, TurmaStatus::ABERTA);

            TurmaHorario::create([
                'id_turma' => $this->turma->id,
                'dia_semana' => 'quarta',
                'hora_inicio' => '10:00',
                'hora_termino' => '11:00',
            ]);

            $alunos = array_map(fn (User $u) => Aluno::create(['id_usuario' => $u->id]), $usuariosAlunos);
            $this->matricular($alunos[0], $this->turma, 'ativa');
            $this->matricular($alunos[1], $this->turma, 'ativa');
            $this->matricular($alunos[2], $this->turma, 'cancelada');
            $this->matricular($alunos[2], $this->turmaAlheia, 'ativa');

            $this->aulas = [
                'hoje_manha' => $this->criarAula($this->turma, '2026-09-30', '10:00', '11:00', 'agendada'),
                'hoje_noite' => $this->criarAula($this->turma, '2026-09-30', '18:00', '19:00', 'agendada'),
                'ontem_com_chamada' => $this->criarAula($this->turma, '2026-09-29', '10:00', '11:00', 'concluida'),
                'segunda_sem_chamada' => $this->criarAula($this->turma, '2026-09-28', '10:00', '11:00', 'concluida'),
                'amanha' => $this->criarAula($this->turma, '2026-10-01', '10:00', '11:00', 'agendada'),
                'semana_passada_cancelada' => $this->criarAula($this->turma, '2026-09-23', '10:00', '11:00', 'cancelada'),
                'proxima_semana' => $this->criarAula($this->turma, '2026-10-06', '10:00', '11:00', 'agendada'),
                'alheia_hoje' => $this->criarAula($this->turmaAlheia, '2026-09-30', '09:00', '10:00', 'agendada'),
            ];

            AulaPresenca::create([
                'id_aula_turma' => $this->aulas['ontem_com_chamada']->id,
                'id_aluno' => $alunos[0]->id,
                'status' => 'presente',
            ]);
        });
    }

    private function criarTurma(Curso $curso, Nivel $nivel, Professor $professor, TurmaStatus $status): Turma
    {
        return Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'maximo_alunos' => 10,
            'status' => $status,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
            'valor_mensalidade' => 300,
        ]);
    }

    private function matricular(Aluno $aluno, Turma $turma, string $status): void
    {
        Matricula::create([
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
            'tipo' => 'turma',
            'data' => '2026-09-01',
            'status' => $status,
        ]);
    }

    private function criarAula(Turma $turma, string $data, string $inicio, string $termino, string $status): AulaTurma
    {
        return AulaTurma::create([
            'id_turma' => $turma->id,
            'id_professor' => $turma->id_professor,
            'data' => $data,
            'hora_inicio' => $inicio,
            'hora_termino' => $termino,
            'status' => $status,
            'valor_hora_aula_aplicado' => 80,
        ]);
    }

    public function test_painel_traz_aulas_de_hoje_da_semana_e_chamadas_pendentes(): void
    {
        $resposta = $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson('/api/professor/me/painel')
            ->assertOk()
            ->assertJsonPath('data.data_referencia', '2026-09-30')
            ->assertJsonPath('data.inicio_semana', '2026-09-28')
            ->assertJsonPath('data.fim_semana', '2026-10-04')
            ->assertJsonPath('data.contadores', [
                'aulas_hoje' => 2,
                'aulas_semana' => 5,
                'chamadas_pendentes' => 2,
                'turmas_ativas' => 1,
                'alunos_ativos' => 2,
            ]);

        $this->assertSame(
            [$this->aulas['hoje_manha']->id, $this->aulas['hoje_noite']->id],
            $resposta->json('data.aulas_hoje.*.id')
        );
        $this->assertSame(
            [
                $this->aulas['segunda_sem_chamada']->id,
                $this->aulas['ontem_com_chamada']->id,
                $this->aulas['hoje_manha']->id,
                $this->aulas['hoje_noite']->id,
                $this->aulas['amanha']->id,
            ],
            $resposta->json('data.aulas_semana.*.id')
        );
        $this->assertSame(
            [$this->aulas['hoje_manha']->id, $this->aulas['segunda_sem_chamada']->id],
            $resposta->json('data.chamadas_pendentes.*.id')
        );
    }

    public function test_painel_traz_alunos_ativos_para_chamada_sem_dados_financeiros(): void
    {
        $resposta = $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson('/api/professor/me/painel')
            ->assertOk()
            ->assertJsonCount(2, 'data.aulas_hoje.0.turma.matriculas')
            ->assertJsonPath('data.aulas_hoje.0.presencas_count', 0);

        $aula = $resposta->json('data.aulas_hoje.0');
        $this->assertArrayNotHasKey('valor_hora_aula_aplicado', $aula);
        $this->assertArrayNotHasKey('valor_mensalidade', $aula['turma']);
        $this->assertNotNull($aula['turma']['matriculas'][0]['aluno']['usuario']['nome']);
    }

    public function test_lista_somente_turmas_do_professor_com_horarios_e_alunos_ativos(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson('/api/professor/me/turmas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->turma->id)
            ->assertJsonPath('data.0.alunos_ativos_count', 2)
            ->assertJsonPath('data.0.horarios.0.dia_semana', 'quarta')
            ->assertJsonPath('data.0.curso.nome', 'Violão')
            ->assertJsonMissingPath('data.0.valor_mensalidade');

        $this->getJson('/api/professor/me/turmas?situacao=encerradas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->turmaConcluida->id);

        $this->getJson('/api/professor/me/turmas?situacao=todas')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/professor/me/turmas?situacao=invalida')->assertUnprocessable();
    }

    public function test_detalhe_da_turma_traz_alunos_e_bloqueia_turma_alheia(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson("/api/professor/me/turmas/{$this->turma->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->turma->id)
            ->assertJsonPath('data.alunos_ativos_count', 2)
            ->assertJsonCount(2, 'data.matriculas')
            ->assertJsonCount(1, 'data.horarios');

        $this->getJson("/api/professor/me/turmas/{$this->turmaAlheia->id}")->assertForbidden();
    }

    public function test_outros_papeis_nao_acessam_painel_nem_turmas_do_professor(): void
    {
        foreach (['secretaria', 'aluno', 'responsavel'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);

            $this->comoUsuario($usuario, $this->instituicao)->getJson('/api/professor/me/painel')->assertForbidden();
            $this->getJson('/api/professor/me/turmas')->assertForbidden();
            $this->getJson("/api/professor/me/turmas/{$this->turma->id}")->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/professor/me/painel')->assertUnauthorized();
    }

    public function test_isolamento_entre_instituicoes(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'professor');

        $turmaDeFora = $this->naInstituicao($outra, function () use ($usuarioOutro): Turma {
            $professor = Professor::create(['id_usuario' => $usuarioOutro->id]);
            $turma = $this->criarTurma(
                Curso::create(['nome' => 'Bateria', 'descricao' => 'Percussão']),
                Nivel::create(['nome' => 'Avançado']),
                $professor,
                TurmaStatus::ABERTA
            );
            $this->criarAula($turma, '2026-09-30', '08:00', '09:00', 'agendada');

            return $turma;
        });

        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson("/api/professor/me/turmas/{$turmaDeFora->id}")
            ->assertNotFound();

        $this->comoUsuario($usuarioOutro, $outra)
            ->getJson('/api/professor/me/painel')
            ->assertOk()
            ->assertJsonPath('data.contadores.aulas_hoje', 1)
            ->assertJsonPath('data.contadores.turmas_ativas', 1);

        $this->getJson('/api/professor/me/turmas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $turmaDeFora->id);
    }
}
