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
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaProfessorFrequenciaTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private const ROTA = '/api/professor/me/relatorios/frequencia';

    private Instituicao $instituicao;

    private User $usuarioProfessor;

    private Professor $professor;

    private Turma $turma;

    private Turma $turmaAlheia;

    /** Bruno: matriculado na turma do professor e na turma alheia. */
    private Aluno $bruno;

    /** Ana: matriculada só na turma do professor. */
    private Aluno $ana;

    /** Carla: matriculada só na turma alheia. */
    private Aluno $carla;

    private Curso $curso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-30 15:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuarioOutroProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Souza']);
        $usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioCarla = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Carla Dias']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioOutroProfessor, $usuarioBruno, $usuarioAna, $usuarioCarla): void {
            $this->curso = Curso::create(['nome' => 'Piano', 'descricao' => 'Teclas']);
            $nivel = Nivel::create(['nome' => 'Básico']);

            $this->professor = Professor::create(['id_usuario' => $this->usuarioProfessor->id]);
            $outroProfessor = Professor::create(['id_usuario' => $usuarioOutroProfessor->id]);

            $this->turma = $this->criarTurma($this->curso, $nivel, $this->professor);
            $this->turmaAlheia = $this->criarTurma($this->curso, $nivel, $outroProfessor);

            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $this->ana = Aluno::create(['id_usuario' => $usuarioAna->id]);
            $this->carla = Aluno::create(['id_usuario' => $usuarioCarla->id]);

            $this->matricular($this->bruno, $this->turma);
            $this->matricular($this->bruno, $this->turmaAlheia);
            $this->matricular($this->ana, $this->turma);
            $this->matricular($this->carla, $this->turmaAlheia);

            $this->presenca($this->bruno, $this->turma, '2026-09-08', 'presente');
            $this->presenca($this->bruno, $this->turma, '2026-09-15', 'ausente');
            $this->presenca($this->bruno, $this->turma, '2026-09-22', 'ausente');
            $this->presenca($this->bruno, $this->turma, '2026-09-29', 'ausente');
            $this->presenca($this->bruno, $this->turmaAlheia, '2026-09-30', 'presente');

            $this->presenca($this->ana, $this->turma, '2026-09-22', 'justificado');
            $this->presenca($this->ana, $this->turma, '2026-09-29', 'presente');
            $this->presenca($this->ana, $this->turma, '2026-09-01', 'ausente', 'cancelada');

            $this->presenca($this->carla, $this->turmaAlheia, '2026-09-29', 'ausente');
        });
    }

    private function criarTurma(Curso $curso, Nivel $nivel, Professor $professor): Turma
    {
        return Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
            'valor_mensalidade' => 300,
        ]);
    }

    private function matricular(Aluno $aluno, Turma $turma): Matricula
    {
        return Matricula::create([
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
            'tipo' => 'turma',
            'data' => '2026-09-01',
            'status' => 'ativa',
        ]);
    }

    private function presenca(Aluno $aluno, ?Turma $turma, string $data, string $status, string $statusAula = 'concluida', ?Professor $professor = null): void
    {
        $aula = AulaTurma::create([
            'id_turma' => $turma?->id,
            'id_curso' => $turma ? null : $this->curso->id,
            'id_professor' => $professor?->id ?? $turma?->id_professor,
            'data' => $data,
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => $statusAula,
        ]);

        AulaPresenca::create(['id_aula_turma' => $aula->id, 'id_aluno' => $aluno->id, 'status' => $status]);
    }

    private function comoProfessor(): static
    {
        return $this->comoUsuario($this->usuarioProfessor, $this->instituicao);
    }

    public function test_professor_ve_frequencia_e_alunos_em_risco_somente_das_proprias_aulas(): void
    {
        $this->comoProfessor()
            ->getJson(self::ROTA)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nome_aluno', 'Ana Lima')
            ->assertJsonPath('data.0.total_aulas', 2)
            ->assertJsonPath('data.0.total_presencas', 1)
            ->assertJsonPath('data.0.total_faltas', 1)
            ->assertJsonPath('data.0.total_justificadas', 1)
            ->assertJsonPath('data.0.faltas_consecutivas', 0)
            ->assertJsonPath('data.0.em_risco', false)
            ->assertJsonPath('data.1.id', $this->bruno->id)
            ->assertJsonPath('data.1.total_aulas', 4)
            ->assertJsonPath('data.1.total_faltas', 3)
            ->assertJsonPath('data.1.taxa_absenteismo', 75)
            ->assertJsonPath('data.1.faltas_consecutivas', 3)
            ->assertJsonPath('data.1.em_risco', true)
            ->assertJsonPath('summary.total_alunos', 2)
            ->assertJsonPath('summary.alunos_em_risco', 1)
            ->assertJsonPath('summary.media_absenteismo', 62.5)
            ->assertJsonPath('filters.data_inicio', '2026-09-01')
            ->assertJsonPath('filters.data_fim', '2026-09-30')
            ->assertJsonPath('filters.limite_faltas', 3);
    }

    public function test_relatorio_da_secretaria_considera_todas_as_aulas_e_isola_faltas_consecutivas_por_turma(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $this->comoUsuario($secretaria, $this->instituicao);

        $geral = $this->getJson('/api/relatorios/academico/absenteismo?data_inicio=2026-09-01&data_fim=2026-09-30')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->json('data');
        $bruno = collect($geral)->firstWhere('id', $this->bruno->id);
        $this->assertSame(5, $bruno['total_aulas']);
        $this->assertSame(0, $bruno['faltas_consecutivas'], 'A presença mais recente, na outra turma, interrompe a sequência.');

        $this->getJson("/api/relatorios/academico/absenteismo?data_inicio=2026-09-01&data_fim=2026-09-30&id_turma={$this->turma->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.id', $this->bruno->id)
            ->assertJsonPath('data.1.faltas_consecutivas', 3)
            ->assertJsonPath('data.1.em_risco', true);
    }

    public function test_filtros_de_turma_periodo_e_limite(): void
    {
        $this->comoProfessor();

        $this->getJson(self::ROTA.'?id_turma='.$this->turma->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('filters.id_turma', $this->turma->id);

        $this->getJson(self::ROTA.'?id_turma='.$this->turmaAlheia->id)->assertForbidden();

        $this->getJson(self::ROTA.'?limite_faltas=4')
            ->assertOk()
            ->assertJsonPath('data.1.faltas_consecutivas', 3)
            ->assertJsonPath('data.1.em_risco', false)
            ->assertJsonPath('summary.alunos_em_risco', 0);

        $this->getJson(self::ROTA.'?data_inicio=2026-09-01&data_fim=2026-09-20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->bruno->id)
            ->assertJsonPath('data.0.total_aulas', 2)
            ->assertJsonPath('data.0.faltas_consecutivas', 1);

        $this->getJson(self::ROTA.'?data_inicio=2026-09-20&data_fim=2026-09-01')->assertJsonValidationErrors('data_fim');
        $this->getJson(self::ROTA.'?limite_faltas=0')->assertJsonValidationErrors('limite_faltas');
    }

    public function test_aulas_individuais_do_professor_entram_no_relatorio(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->presenca($this->carla, null, '2026-09-24', 'presente', professor: $this->professor);
        });

        $this->comoProfessor()
            ->getJson(self::ROTA)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.2.nome_aluno', 'Carla Dias')
            ->assertJsonPath('data.2.total_aulas', 1)
            ->assertJsonPath('data.2.faltas_consecutivas', 0);
    }

    public function test_acesso_exige_login_e_papel_de_professor(): void
    {
        $this->getJson(self::ROTA, ['X-Tenant-Slug' => $this->instituicao->slug])->assertUnauthorized();

        foreach (['secretaria', 'admin', 'aluno'] as $papel) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $papel);
            $this->comoUsuario($usuario, $this->instituicao)->getJson(self::ROTA)->assertForbidden();
        }
    }

    public function test_isolamento_entre_instituicoes(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'professor');
        $usuarioAlunoDeFora = $this->criarUsuarioNaInstituicao($outra, 'aluno');

        $turmaDeFora = $this->naInstituicao($outra, function () use ($usuarioOutro, $usuarioAlunoDeFora): Turma {
            $turma = $this->criarTurma(
                Curso::create(['nome' => 'Bateria', 'descricao' => 'Percussão']),
                Nivel::create(['nome' => 'Avançado']),
                Professor::create(['id_usuario' => $usuarioOutro->id])
            );
            $this->presenca(Aluno::create(['id_usuario' => $usuarioAlunoDeFora->id]), $turma, '2026-09-29', 'ausente');

            return $turma;
        });

        $this->comoProfessor()
            ->getJson(self::ROTA)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(self::ROTA.'?id_turma='.$turmaDeFora->id)->assertJsonValidationErrors('id_turma');

        $this->comoUsuario($usuarioOutro, $outra)
            ->getJson(self::ROTA)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.faltas_consecutivas', 1);
    }
}
