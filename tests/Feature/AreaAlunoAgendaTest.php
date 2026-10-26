<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\ApresentacaoAluno;
use App\Modules\Espetaculos\Models\Ensaio;
use App\Modules\Espetaculos\Models\Espetaculo;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoAgendaTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $bruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno, $usuarioProfessor): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $professor = Professor::create(['id_usuario' => $usuarioProfessor->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $turmaAna = $this->turma($curso, $nivel, $professor, 'Turma Ana');
            $turmaBruno = $this->turma($curso, $nivel, $professor, 'Turma Bruno');
            $turmaAntiga = $this->turma($curso, $nivel, $professor, 'Turma antiga');
            $this->matricular($this->ana, $turmaAna, 'ativa');
            $this->matricular($this->bruno, $turmaBruno, 'ativa');
            $this->matricular($this->ana, $turmaAntiga, 'cancelada');

            $this->aula($turmaAna, $professor, '2026-10-27', '14:00', '15:00', 'agendada');
            $this->aula($turmaAna, $professor, '2026-10-28', '14:00', '15:00', 'concluida');
            $this->aula($turmaAna, $professor, '2026-10-29', '14:00', '15:00', 'cancelada');
            $original = $this->aula($turmaAna, $professor, '2026-10-16', '14:00', '15:00', 'cancelada');
            $gerada = $this->aula($turmaAna, $professor, '2026-11-02', '16:00', '17:00', 'agendada', 'reposicao');
            $this->aula($turmaAna, $professor, '2026-10-27', '08:00', '09:00', 'agendada', 'reposicao', $this->bruno->id);
            $this->aula($turmaBruno, $professor, '2026-10-27', '09:00', '10:00', 'agendada');
            $this->aula($turmaAntiga, $professor, '2026-10-27', '07:00', '08:00', 'agendada');

            SolicitacaoAula::create([
                'id_aula_turma' => $original->id,
                'id_professor' => $professor->id,
                'tipo' => SolicitacaoAula::TIPO_REPOSICAO,
                'status' => SolicitacaoAula::STATUS_APROVADA,
                'decidido_em' => '2026-10-15 12:00:00',
                'id_aula_gerada' => $gerada->id,
            ]);

            $passado = Espetaculo::create(['titulo' => 'Mostra', 'data_evento' => '2026-10-10', 'local' => 'Hall', 'status' => 'concluido']);
            $recital = Espetaculo::create(['titulo' => 'Recital', 'data_evento' => '2026-10-20', 'local' => 'Teatro', 'status' => 'planejamento']);
            $futuro = Espetaculo::create(['titulo' => 'Gala', 'data_evento' => '2026-11-20', 'status' => 'planejamento']);
            $doBruno = Espetaculo::create(['titulo' => 'Sarau', 'data_evento' => '2026-10-20', 'status' => 'planejamento']);

            $pecaAntiga = Apresentacao::create(['id_espetaculo' => $passado->id, 'id_turma' => $turmaAna->id, 'titulo_musica' => 'Peça antiga']);
            $pecaAna = Apresentacao::create(['id_espetaculo' => $recital->id, 'id_turma' => $turmaAna->id, 'titulo_musica' => 'Peça da Ana']);
            Apresentacao::create(['id_espetaculo' => $futuro->id, 'id_turma' => $turmaAna->id, 'titulo_musica' => 'Peça futura']);
            $pecaBruno = Apresentacao::create(['id_espetaculo' => $doBruno->id, 'id_turma' => $turmaBruno->id, 'titulo_musica' => 'Peça do Bruno']);
            ApresentacaoAluno::create(['id_apresentacao' => $pecaAntiga->id, 'id_aluno' => $this->ana->id]);
            ApresentacaoAluno::create(['id_apresentacao' => $pecaAna->id, 'id_aluno' => $this->ana->id]);
            ApresentacaoAluno::create(['id_apresentacao' => $pecaBruno->id, 'id_aluno' => $this->bruno->id]);

            Ensaio::create([
                'id_apresentacao' => $pecaAna->id,
                'id_professor' => $professor->id,
                'data' => '2026-10-27',
                'hora_inicio' => '10:00',
                'hora_termino' => '11:00',
                'local' => 'Sala 2',
                'observacoes' => 'nota interna do professor',
            ]);
            Ensaio::create([
                'id_apresentacao' => $pecaBruno->id,
                'id_professor' => $professor->id,
                'data' => '2026-10-27',
                'hora_inicio' => '18:00',
                'hora_termino' => '19:00',
                'local' => 'Sala do Bruno',
            ]);
        });
    }

    public function test_agenda_une_aulas_remarcacoes_apresentacoes_e_ensaios(): void
    {
        $itens = collect($this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/agenda?inicio=2026-10-01&fim=2026-11-02')
            ->assertOk()
            ->json('data.itens'));

        $this->assertCount(8, $itens);
        $this->assertSame(
            ['2026-10-10', '2026-10-16', '2026-10-20', '2026-10-27', '2026-10-27', '2026-10-28', '2026-10-29', '2026-11-02'],
            $itens->pluck('data')->all()
        );

        $remarcada = $itens->firstWhere('data', '2026-10-16');
        $this->assertTrue($remarcada['remarcada']);
        $this->assertSame('cancelada', $remarcada['status']);
        $this->assertSame('2026-11-02', $remarcada['remarcacao']['para']['data']);
        $this->assertSame('16:00', $remarcada['remarcacao']['para']['hora_inicio']);

        $nova = $itens->firstWhere('data', '2026-11-02');
        $this->assertTrue($nova['remarcada']);
        $this->assertSame('2026-10-16', $nova['remarcacao']['de']['data']);

        $cancelada = $itens->firstWhere('data', '2026-10-29');
        $this->assertFalse($cancelada['remarcada']);
        $this->assertNull($cancelada['remarcacao']);

        $this->assertSame('ensaio', $itens->first(fn (array $item) => $item['data'] === '2026-10-27' && $item['hora_inicio'] === '10:00')['tipo']);
        $this->assertSame('aula', $itens->first(fn (array $item) => $item['data'] === '2026-10-27' && $item['hora_inicio'] === '14:00')['tipo']);
        $this->assertSame('Peça da Ana', $itens->firstWhere('data', '2026-10-20')['titulo']);
        $this->assertSame('Peça antiga', $itens->firstWhere('data', '2026-10-10')['titulo']);
        $this->assertTrue($itens->every(fn (array $item) => $item['titulo'] !== 'Turma Bruno' && $item['titulo'] !== 'Peça do Bruno' && $item['titulo'] !== 'Peça futura'));
        $this->assertFalse($itens->contains(fn (array $item) => in_array($item['hora_inicio'], ['07:00', '08:00', '09:00', '18:00'], true)));
        $this->assertStringNotContainsString('nota interna do professor', json_encode($itens));
        $this->assertStringNotContainsString('valor_mensalidade', json_encode($itens));
    }

    public function test_sem_modulo_de_espetaculos_a_agenda_fica_so_com_as_aulas(): void
    {
        $plano = PlanoAssinatura::create([
            'slug' => 'sem-espetaculos',
            'nome' => 'Sem espetáculos',
            'preco_mensal' => 10,
            'limite_alunos' => 50,
            'modulos' => ['leads'],
            'ativo' => true,
        ]);
        $this->instituicao->update([
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            'id_plano_assinatura' => $plano->id,
            'assinatura_inicia_em' => '2026-01-01',
        ]);

        $agenda = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/agenda?inicio=2026-10-01&fim=2026-11-02')
            ->assertOk()
            ->json('data');

        $this->assertFalse($agenda['espetaculos']);
        $this->assertCount(5, $agenda['itens']);
        $this->assertTrue(collect($agenda['itens'])->every(fn (array $item) => $item['tipo'] === 'aula'));
    }

    public function test_periodo_invalido_retorna_422(): void
    {
        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/agenda')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['inicio', 'fim']);

        $this->getJson('/api/aluno/me/agenda?inicio=2026-01-01&fim=2026-03-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('fim');
    }

    public function test_responsavel_consulta_o_dependente_e_os_demais_papeis_nao(): void
    {
        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/agenda?inicio=2026-10-26&fim=2026-11-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $semana = $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/agenda?inicio=2026-10-26&fim=2026-11-01&id_aluno='.$this->ana->id)
            ->assertOk()
            ->json('data.itens');

        $this->assertCount(4, $semana);
        $this->assertTrue(collect($semana)->contains(fn (array $item) => $item['titulo'] === 'Turma Ana' && $item['data'] === '2026-10-27'));

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/agenda?inicio=2026-10-26&fim=2026-11-01&id_aluno='.$this->bruno->id)
            ->assertForbidden();

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/agenda?inicio=2026-10-26&fim=2026-11-01')
                ->assertForbidden();
        }
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/agenda?inicio=2026-10-26&fim=2026-11-01')
            ->assertUnauthorized();
    }

    public function test_aluno_de_outra_instituicao_recebe_403(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'aluno', ['nome' => 'Aluno de Fora']);
        $this->naInstituicao($outra, fn () => Aluno::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/aluno/me/agenda?inicio=2026-10-26&fim=2026-11-01')
            ->assertForbidden();
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
            'valor_mensalidade' => 350,
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

    private function aula(
        Turma $turma,
        Professor $professor,
        string $data,
        string $inicio,
        string $termino,
        string $status,
        string $tipo = 'regular',
        ?int $idAlunoEspecifico = null,
    ): AulaTurma {
        return AulaTurma::create([
            'id_turma' => $turma->id,
            'id_curso' => $turma->id_curso,
            'id_professor' => $professor->id,
            'data' => $data,
            'hora_inicio' => $inicio,
            'hora_termino' => $termino,
            'status' => $status,
            'tipo' => $tipo,
            'id_aluno_especifico' => $idAlunoEspecifico,
        ]);
    }
}
