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
use App\Modules\Academico\Models\TurmaHorario;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
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

class AreaAlunoPainelTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioBruno;

    private User $usuarioCarla;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $bruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-26 09:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $this->usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioCarla = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Carla Dias']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioProfessor): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $professor = Professor::create(['id_usuario' => $usuarioProfessor->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $this->usuarioBruno->id]);
            $carla = Aluno::create(['id_usuario' => $this->usuarioCarla->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $turmaAna = $this->turma($curso, $nivel, $professor, 'Turma Ana');
            $turmaBruno = $this->turma($curso, $nivel, $professor, 'Turma Bruno');
            $turmaCarla = $this->turma($curso, $nivel, $professor, 'Turma Carla');
            $turmaEncerrada = $this->turma($curso, $nivel, $professor, 'Turma antiga');

            TurmaHorario::create(['id_turma' => $turmaAna->id, 'dia_semana' => 'terca', 'hora_inicio' => '14:00', 'hora_termino' => '15:00']);
            TurmaHorario::create(['id_turma' => $turmaCarla->id, 'dia_semana' => 'quarta', 'hora_inicio' => '10:00', 'hora_termino' => '11:00']);

            $this->matricular($this->ana, $turmaAna, 'ativa');
            $this->matricular($this->bruno, $turmaBruno, 'ativa');
            $this->matricular($carla, $turmaCarla, 'ativa');
            $this->matricular($this->ana, $turmaEncerrada, 'cancelada');

            $this->aula($turmaAna, $professor, '2026-10-27', '14:00', '15:00', 'agendada', 'regular');
            $this->aula($turmaAna, $professor, '2026-10-20', '14:00', '15:00', 'cancelada', 'regular');
            $original = $this->aula($turmaAna, $professor, '2026-10-13', '14:00', '15:00', 'cancelada', 'regular');
            $gerada = $this->aula($turmaAna, $professor, '2026-10-28', '16:00', '17:00', 'agendada', 'reposicao');
            $this->aula($turmaAna, $professor, '2026-10-27', '08:00', '09:00', 'agendada', 'reposicao', $this->bruno->id);
            $this->aula($turmaBruno, $professor, '2026-10-27', '08:00', '09:00', 'agendada', 'regular');
            $this->aula($turmaEncerrada, $professor, '2026-10-27', '07:00', '08:00', 'agendada', 'regular');

            SolicitacaoAula::create([
                'id_aula_turma' => $original->id,
                'id_professor' => $professor->id,
                'tipo' => SolicitacaoAula::TIPO_REPOSICAO,
                'status' => SolicitacaoAula::STATUS_APROVADA,
                'decidido_em' => '2026-10-25 12:00:00',
                'id_aula_gerada' => $gerada->id,
            ]);

            $categoria = CategoriaConta::firstOrCreate(['nome' => 'Mensalidades'], ['tipo' => 'receita']);
            $this->conta($categoria->id, $this->ana->id, 'Mensalidade da Ana', 100, '2026-10-20', 'pendente', 'segredo da secretaria');
            $this->conta($categoria->id, $this->ana->id, 'Mensalidade de novembro', 80, '2026-11-10', 'pendente');
            $this->conta($categoria->id, $this->ana->id, 'Mensalidade paga', 90, '2026-09-10', 'pago');
            $this->conta($categoria->id, $this->bruno->id, 'Mensalidade do Bruno', 50, '2026-10-01', 'pendente');

            $recital = Espetaculo::create(['titulo' => 'Recital', 'data_evento' => '2026-11-20', 'local' => 'Teatro', 'status' => 'planejamento']);
            $passado = Espetaculo::create(['titulo' => 'Mostra antiga', 'data_evento' => '2026-09-01', 'status' => 'concluido']);
            $doBruno = Espetaculo::create(['titulo' => 'Sarau', 'data_evento' => '2026-11-01', 'status' => 'planejamento']);
            $pecaAna = Apresentacao::create(['id_espetaculo' => $recital->id, 'id_turma' => $turmaAna->id, 'titulo_musica' => 'Peça da Ana']);
            Apresentacao::create(['id_espetaculo' => $passado->id, 'id_turma' => $turmaAna->id, 'titulo_musica' => 'Peça antiga']);
            $pecaBruno = Apresentacao::create(['id_espetaculo' => $doBruno->id, 'id_turma' => $turmaBruno->id, 'titulo_musica' => 'Peça do Bruno']);
            ApresentacaoAluno::create(['id_apresentacao' => $pecaAna->id, 'id_aluno' => $this->ana->id]);
            ApresentacaoAluno::create(['id_apresentacao' => $pecaBruno->id, 'id_aluno' => $this->bruno->id]);
        });
    }

    public function test_aluno_ve_o_painel_da_propria_rotina(): void
    {
        $painel = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/painel')
            ->assertOk()
            ->json('data');

        $this->assertSame('2026-10-27', $painel['proxima_aula']['data']);
        $this->assertSame('14:00', $painel['proxima_aula']['hora_inicio']);
        $this->assertSame('Turma Ana', $painel['proxima_aula']['turma']);
        $this->assertSame('aula', $painel['proxima_aula']['origem']);
        $this->assertCount(2, $painel['proximas_aulas']);
        $this->assertSame('2026-10-28', $painel['proximas_aulas'][1]['data']);

        $this->assertSame('Mensalidade da Ana', $painel['financeiro']['proxima_mensalidade']['descricao']);
        $this->assertSame('atrasada', $painel['financeiro']['proxima_mensalidade']['situacao']);
        $this->assertSame(100, $painel['financeiro']['proxima_mensalidade']['valor']);
        $this->assertArrayNotHasKey('observacoes', $painel['financeiro']['proxima_mensalidade']);
        $this->assertSame(2, $painel['financeiro']['em_aberto']);
        $this->assertSame(1, $painel['financeiro']['atrasadas']);
        $this->assertEquals(180, $painel['financeiro']['valor_em_aberto']);
        $this->assertEquals(100, $painel['financeiro']['valor_atrasado']);

        $this->assertTrue($painel['apresentacao']['disponivel']);
        $this->assertSame('Peça da Ana', $painel['apresentacao']['proxima']['titulo_musica']);
        $this->assertSame('2026-11-20', $painel['apresentacao']['proxima']['data_evento']);
        $this->assertSame('Teatro', $painel['apresentacao']['proxima']['local']);

        $avisos = collect($painel['avisos']);
        $this->assertCount(2, $avisos);
        $this->assertTrue($avisos->contains(fn (array $aviso) => $aviso['tipo'] === 'remarcada' && str_contains($aviso['mensagem'], '28/10')));
        $this->assertTrue($avisos->contains(fn (array $aviso) => $aviso['tipo'] === 'cancelada' && str_contains($aviso['mensagem'], '20/10')));
        $this->assertFalse($avisos->contains(fn (array $aviso) => $aviso['tipo'] === 'cancelada' && str_contains($aviso['mensagem'], '13/10')));
    }

    public function test_sem_aulas_geradas_usa_o_horario_da_turma(): void
    {
        $painel = $this->comoUsuario($this->usuarioCarla, $this->instituicao)
            ->getJson('/api/aluno/me/painel')
            ->assertOk()
            ->json('data');

        $this->assertSame('horario', $painel['proxima_aula']['origem']);
        $this->assertSame('2026-10-28', $painel['proxima_aula']['data']);
        $this->assertSame('10:00', $painel['proxima_aula']['hora_inicio']);
        $this->assertSame('quarta', $painel['proxima_aula']['dia_semana']);
        $this->assertSame('Turma Carla', $painel['proxima_aula']['turma']);
        $this->assertSame([], $painel['proximas_aulas']);
    }

    public function test_modulos_ausentes_deixam_financeiro_e_apresentacao_vazios(): void
    {
        $plano = PlanoAssinatura::create([
            'slug' => 'sem-extras',
            'nome' => 'Sem extras',
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

        $painel = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/painel')
            ->assertOk()
            ->json('data');

        $this->assertFalse($painel['financeiro']['disponivel']);
        $this->assertNull($painel['financeiro']['proxima_mensalidade']);
        $this->assertSame(0, $painel['financeiro']['em_aberto']);
        $this->assertFalse($painel['apresentacao']['disponivel']);
        $this->assertNull($painel['apresentacao']['proxima']);
        $this->assertSame('2026-10-27', $painel['proxima_aula']['data']);
    }

    public function test_responsavel_consulta_o_dependente_e_os_demais_papeis_nao(): void
    {
        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/painel')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/painel?id_aluno='.$this->ana->id)
            ->assertOk()
            ->assertJsonPath('data.proxima_aula.turma', 'Turma Ana');

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/painel?id_aluno='.$this->bruno->id)
            ->assertForbidden();

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/painel')
                ->assertForbidden();
        }
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/painel')
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
            ->getJson('/api/aluno/me/painel')
            ->assertForbidden();

        $this->comoUsuario($usuarioOutro, $outra)
            ->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/painel')
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
        string $tipo,
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

    private function conta(int $categoriaId, int $alunoId, string $descricao, float $valor, string $vencimento, string $status, ?string $observacoes = null): void
    {
        Conta::create([
            'id_categoria' => $categoriaId,
            'id_aluno' => $alunoId,
            'descricao' => $descricao,
            'valor' => $valor,
            'data_vencimento' => $vencimento,
            'status' => $status,
            'tipo' => 'receita',
            'observacoes' => $observacoes,
        ]);
    }
}
