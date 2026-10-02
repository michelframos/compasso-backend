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
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\ApresentacaoAluno;
use App\Modules\Espetaculos\Models\Ensaio;
use App\Modules\Espetaculos\Models\Espetaculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaProfessorEspetaculosTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private const ROTA = '/api/professor/me/apresentacoes';

    private Instituicao $instituicao;

    private User $usuarioProfessor;

    private User $secretaria;

    private Professor $professor;

    private Professor $outroProfessor;

    private Aluno $ana;

    private Aluno $carla;

    private Curso $curso;

    private Nivel $nivel;

    private Turma $turma;

    /** Concluído, em junho: apresentação da turma do professor. */
    private Apresentacao $passada;

    /** Espetáculo de 20/11: apresentação da turma do professor. */
    private Apresentacao $daMinhaTurma;

    /** Mesmo espetáculo: turma alheia, com Ana (aluna do professor) e Carla. */
    private Apresentacao $comMeuAluno;

    /** Mesmo espetáculo: turma alheia, só com Carla. */
    private Apresentacao $alheia;

    /** Espetáculo cancelado em dezembro: turma do professor. */
    private Apresentacao $cancelada;

    /** Espetáculo só com apresentação alheia. */
    private Espetaculo $espetaculoAlheio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-30 15:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $usuarioOutroProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Otto Prof']);
        $usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioCarla = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Carla Dias']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioOutroProfessor, $usuarioAna, $usuarioCarla): void {
            $this->curso = Curso::create(['nome' => 'Dança', 'descricao' => 'Ballet']);
            $this->nivel = Nivel::create(['nome' => 'Básico']);

            $this->professor = Professor::create(['id_usuario' => $this->usuarioProfessor->id]);
            $this->outroProfessor = Professor::create(['id_usuario' => $usuarioOutroProfessor->id]);

            $this->turma = $this->criarTurma($this->professor);
            $turmaAlheia = $this->criarTurma($this->outroProfessor);

            $this->ana = Aluno::create(['id_usuario' => $usuarioAna->id]);
            $this->carla = Aluno::create(['id_usuario' => $usuarioCarla->id]);
            $this->matricular($this->ana, $this->turma);
            $this->matricular($this->carla, $turmaAlheia);

            $junina = Espetaculo::create(['titulo' => 'Festa Junina', 'data_evento' => '2026-06-10', 'status' => 'concluido']);
            $natal = Espetaculo::create(['titulo' => 'Natal', 'data_evento' => '2026-11-20', 'local' => 'Teatro', 'status' => 'ensaios']);
            $gala = Espetaculo::create(['titulo' => 'Gala', 'data_evento' => '2026-12-05', 'status' => 'cancelado']);
            $this->espetaculoAlheio = Espetaculo::create(['titulo' => 'Recital', 'data_evento' => '2026-10-25', 'status' => 'planejamento']);

            $this->passada = $this->apresentacao($junina, $this->turma, 'Asa Branca', 1);
            $this->daMinhaTurma = $this->apresentacao($natal, $this->turma, 'Jingle Bells', 1);
            $this->comMeuAluno = $this->apresentacao($natal, $turmaAlheia, 'Noite Feliz', 2);
            $this->alheia = $this->apresentacao($natal, $turmaAlheia, 'Bate o Sino', 3);
            $this->cancelada = $this->apresentacao($gala, $this->turma, 'Valsa', 1);
            $this->apresentacao($this->espetaculoAlheio, $turmaAlheia, 'Bolero', 1);

            $this->participar($this->comMeuAluno, $this->ana, ['tamanho_figurino' => 'P', 'recebeu_figurino' => true]);
            $this->participar($this->comMeuAluno, $this->carla);
            $this->participar($this->alheia, $this->carla);
        });
    }

    private function criarTurma(Professor $professor): Turma
    {
        return Turma::create([
            'id_curso' => $this->curso->id,
            'id_nivel' => $this->nivel->id,
            'id_professor' => $professor->id,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
            'valor_mensalidade' => 300,
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

    private function apresentacao(Espetaculo $espetaculo, ?Turma $turma, string $musica, int $ordem): Apresentacao
    {
        return Apresentacao::create([
            'id_espetaculo' => $espetaculo->id,
            'id_turma' => $turma?->id,
            'titulo_musica' => $musica,
            'ordem_entrada' => $ordem,
        ]);
    }

    private function participar(Apresentacao $apresentacao, Aluno $aluno, array $dados = []): ApresentacaoAluno
    {
        return ApresentacaoAluno::create([
            'id_apresentacao' => $apresentacao->id,
            'id_aluno' => $aluno->id,
            'valor_figurino' => 150,
            ...$dados,
        ]);
    }

    private function ensaio(Apresentacao $apresentacao, ?Professor $professor, string $data = '2026-10-15', string $inicio = '14:00'): Ensaio
    {
        return $this->naInstituicao($this->instituicao, fn () => Ensaio::create([
            'id_apresentacao' => $apresentacao->id,
            'id_professor' => $professor?->id,
            'data' => $data,
            'hora_inicio' => $inicio,
            'hora_termino' => '16:00',
            'local' => 'Sala 1',
        ]));
    }

    private function dadosEnsaio(Apresentacao $apresentacao, array $dados = []): array
    {
        return [
            'id_apresentacao' => $apresentacao->id,
            'data' => '2026-10-10',
            'hora_inicio' => '14:00',
            'hora_termino' => '15:30',
            'local' => 'Sala 2',
            ...$dados,
        ];
    }

    private function comoProfessor(): static
    {
        return $this->comoUsuario($this->usuarioProfessor, $this->instituicao);
    }

    public function test_minhas_apresentacoes_por_situacao(): void
    {
        $this->ensaio($this->daMinhaTurma, $this->professor, '2026-10-20');
        $this->ensaio($this->daMinhaTurma, $this->professor, '2026-10-08');
        $this->ensaio($this->daMinhaTurma, $this->professor, '2026-09-20');

        $this->comoProfessor()
            ->getJson(self::ROTA)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $this->daMinhaTurma->id)
            ->assertJsonPath('data.0.turma.minha', true)
            ->assertJsonPath('data.0.turma.curso.nome', 'Dança')
            ->assertJsonPath('data.0.espetaculo.titulo', 'Natal')
            ->assertJsonPath('data.0.espetaculo.data_evento', '2026-11-20')
            ->assertJsonPath('data.0.proximo_ensaio.data', '2026-10-08')
            ->assertJsonPath('data.0.ensaios_futuros', 2)
            ->assertJsonPath('data.0.pode_agendar_ensaio', true)
            ->assertJsonPath('data.1.id', $this->comMeuAluno->id)
            ->assertJsonPath('data.1.turma.minha', false)
            ->assertJsonPath('data.1.total_participantes', 2)
            ->assertJsonPath('data.1.meus_alunos', 1)
            ->assertJsonPath('data.1.proximo_ensaio', null)
            ->assertJsonPath('data.2.id', $this->cancelada->id)
            ->assertJsonMissingPath('data.0.participantes');

        $this->getJson(self::ROTA.'?situacao=passadas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->passada->id);

        $this->getJson(self::ROTA.'?situacao=todas')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson(self::ROTA.'?situacao=ontem')->assertJsonValidationErrors('situacao');
    }

    public function test_detalhe_traz_participantes_sem_valores_e_ensaios(): void
    {
        $meu = $this->ensaio($this->comMeuAluno, $this->professor, '2026-10-12');
        $doOutro = $this->ensaio($this->comMeuAluno, $this->outroProfessor, '2026-10-05');

        $resposta = $this->comoProfessor()
            ->getJson(self::ROTA.'/'.$this->comMeuAluno->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->comMeuAluno->id)
            ->assertJsonCount(2, 'data.participantes')
            ->assertJsonPath('data.participantes.0.nome', 'Ana Lima')
            ->assertJsonPath('data.participantes.0.meu_aluno', true)
            ->assertJsonPath('data.participantes.0.tamanho_figurino', 'P')
            ->assertJsonPath('data.participantes.0.recebeu_figurino', true)
            ->assertJsonPath('data.participantes.1.nome', 'Carla Dias')
            ->assertJsonPath('data.participantes.1.meu_aluno', false)
            ->assertJsonCount(2, 'data.ensaios')
            ->assertJsonPath('data.ensaios.0.id', $doOutro->id)
            ->assertJsonPath('data.ensaios.0.pode_editar', false)
            ->assertJsonPath('data.ensaios.0.professor.nome', 'Otto Prof')
            ->assertJsonPath('data.ensaios.1.id', $meu->id)
            ->assertJsonPath('data.ensaios.1.pode_editar', true)
            ->assertJsonPath('data.proximo_ensaio.id', $doOutro->id);

        $this->assertStringNotContainsString('valor_figurino', $resposta->getContent());
        $this->assertStringNotContainsString('pago_figurino', $resposta->getContent());

        $this->getJson(self::ROTA.'/'.$this->alheia->id)->assertForbidden();
    }

    public function test_endpoints_gerais_de_espetaculos_ficam_restritos_ao_professor(): void
    {
        $this->comoProfessor();

        $this->getJson('/api/espetaculos')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/espetaculos/'.$this->espetaculoAlheio->id)->assertForbidden();
        $this->getJson('/api/apresentacoes')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/apresentacoes/'.$this->comMeuAluno->id)->assertOk();
        $this->getJson('/api/apresentacoes/'.$this->alheia->id)->assertForbidden();
        $participacoes = $this->getJson('/api/apresentacoes-alunos')->assertOk()->assertJsonCount(2, 'data');
        $this->assertStringNotContainsString('valor_figurino', $participacoes->getContent());
        $this->assertStringNotContainsString('fatura_gerada', $participacoes->getContent());

        $participacaoAlheia = ApresentacaoAluno::withoutGlobalScopes()->where('id_apresentacao', $this->alheia->id)->firstOrFail();
        $this->getJson('/api/apresentacoes-alunos/'.$participacaoAlheia->id)->assertForbidden();

        $this->comoUsuario($this->secretaria, $this->instituicao);
        $this->getJson('/api/espetaculos')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/apresentacoes')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/api/apresentacoes/'.$this->alheia->id)->assertOk();
        $this->getJson('/api/apresentacoes-alunos/'.$participacaoAlheia->id)
            ->assertOk()
            ->assertJsonPath('data.valor_figurino', '150.00');
    }

    public function test_professor_agenda_ensaio_como_condutor_nas_apresentacoes_visiveis(): void
    {
        $this->comoProfessor()
            ->postJson('/api/ensaios', $this->dadosEnsaio($this->comMeuAluno, ['id_professor' => $this->outroProfessor->id]))
            ->assertCreated()
            ->assertJsonPath('data.id_apresentacao', $this->comMeuAluno->id)
            ->assertJsonPath('data.id_professor', $this->professor->id)
            ->assertJsonPath('data.data', '2026-10-10')
            ->assertJsonPath('data.hora_inicio', '14:00')
            ->assertJsonPath('data.hora_termino', '15:30')
            ->assertJsonPath('data.pode_editar', true)
            ->assertJsonPath('data.apresentacao.espetaculo.titulo', 'Natal');

        $this->assertDatabaseHas('ensaios', [
            'id_instituicao' => $this->instituicao->id,
            'id_apresentacao' => $this->comMeuAluno->id,
            'id_professor' => $this->professor->id,
        ]);

        $this->postJson('/api/ensaios', $this->dadosEnsaio($this->alheia))->assertForbidden();
    }

    public function test_regras_de_data_e_horario_do_ensaio(): void
    {
        $this->comoProfessor();

        $this->postJson('/api/ensaios', $this->dadosEnsaio($this->daMinhaTurma, ['data' => '2026-09-29']))
            ->assertJsonValidationErrors('data');
        $this->postJson('/api/ensaios', $this->dadosEnsaio($this->daMinhaTurma, ['data' => '2026-11-21']))
            ->assertJsonValidationErrors('data');
        $this->postJson('/api/ensaios', $this->dadosEnsaio($this->daMinhaTurma, ['data' => '2026-11-20']))
            ->assertCreated();
        $this->postJson('/api/ensaios', $this->dadosEnsaio($this->daMinhaTurma, ['hora_termino' => '13:00']))
            ->assertJsonValidationErrors('hora_termino');
        $this->postJson('/api/ensaios', $this->dadosEnsaio($this->cancelada))
            ->assertJsonValidationErrors('id_apresentacao');
        $this->postJson('/api/ensaios', $this->dadosEnsaio($this->passada))
            ->assertJsonValidationErrors('id_apresentacao');

        $antigo = $this->ensaio($this->daMinhaTurma, $this->professor, '2026-09-20');
        $this->putJson('/api/ensaios/'.$antigo->id, ['data' => '2026-09-20', 'hora_inicio' => '09:00', 'hora_termino' => '10:00'])
            ->assertOk()
            ->assertJsonPath('data.hora_inicio', '09:00');
        $this->putJson('/api/ensaios/'.$antigo->id, ['data' => '2026-09-25', 'hora_inicio' => '09:00', 'hora_termino' => '10:00'])
            ->assertJsonValidationErrors('data');
    }

    public function test_professor_edita_e_exclui_apenas_os_ensaios_que_conduz(): void
    {
        $meu = $this->ensaio($this->comMeuAluno, $this->professor);
        $doOutro = $this->ensaio($this->comMeuAluno, $this->outroProfessor);
        $payload = ['data' => '2026-10-16', 'hora_inicio' => '10:00', 'hora_termino' => '11:00', 'local' => 'Palco', 'id_professor' => $this->outroProfessor->id];

        $this->comoProfessor();
        $this->putJson('/api/ensaios/'.$doOutro->id, $payload)->assertForbidden();
        $this->deleteJson('/api/ensaios/'.$doOutro->id)->assertForbidden();

        $this->putJson('/api/ensaios/'.$meu->id, $payload)
            ->assertOk()
            ->assertJsonPath('data.data', '2026-10-16')
            ->assertJsonPath('data.local', 'Palco')
            ->assertJsonPath('data.id_professor', $this->professor->id);
        $this->deleteJson('/api/ensaios/'.$meu->id)->assertNoContent();
        $this->assertSoftDeleted('ensaios', ['id' => $meu->id]);

        $this->comoUsuario($this->secretaria, $this->instituicao);
        $this->putJson('/api/ensaios/'.$doOutro->id, [...$payload, 'id_professor' => $this->professor->id])
            ->assertOk()
            ->assertJsonPath('data.id_professor', $this->professor->id)
            ->assertJsonPath('data.pode_editar', true);
        $this->postJson('/api/ensaios', $this->dadosEnsaio($this->alheia, ['id_professor' => $this->outroProfessor->id]))
            ->assertCreated()
            ->assertJsonPath('data.id_professor', $this->outroProfessor->id);
        $this->deleteJson('/api/ensaios/'.$doOutro->id)->assertNoContent();
    }

    public function test_agenda_de_ensaios_com_visibilidade_e_filtros(): void
    {
        $proprio = $this->ensaio($this->daMinhaTurma, $this->professor, '2026-10-20');
        $comMeuAluno = $this->ensaio($this->comMeuAluno, $this->outroProfessor, '2026-10-05');
        $conduzidoNaAlheia = $this->ensaio($this->alheia, $this->professor, '2026-10-12');
        $this->ensaio($this->alheia, $this->outroProfessor, '2026-10-01');
        $passado = $this->ensaio($this->daMinhaTurma, $this->professor, '2026-09-10');

        $this->comoProfessor()
            ->getJson('/api/ensaios')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.id', $passado->id)
            ->assertJsonPath('data.1.id', $comMeuAluno->id)
            ->assertJsonPath('data.2.id', $conduzidoNaAlheia->id)
            ->assertJsonPath('data.3.id', $proprio->id)
            ->assertJsonPath('data.3.apresentacao.titulo_musica', 'Jingle Bells');

        $this->getJson('/api/ensaios?data_inicio=2026-09-30&data_fim=2026-10-15')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->getJson('/api/ensaios?id_apresentacao='.$this->daMinhaTurma->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->getJson('/api/ensaios?data_inicio=2026-10-15&data_fim=2026-10-01')
            ->assertJsonValidationErrors('data_fim');

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->getJson('/api/ensaios')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_acesso_exige_login_e_papel_permitido(): void
    {
        $headers = ['X-Tenant-Slug' => $this->instituicao->slug];
        $this->getJson(self::ROTA, $headers)->assertUnauthorized();
        $this->getJson('/api/ensaios', $headers)->assertUnauthorized();
        $this->postJson('/api/ensaios', $this->dadosEnsaio($this->daMinhaTurma), $headers)->assertUnauthorized();

        $aluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $this->comoUsuario($aluno, $this->instituicao);
        $this->getJson(self::ROTA)->assertForbidden();
        $this->getJson('/api/ensaios')->assertForbidden();
        $this->postJson('/api/ensaios', $this->dadosEnsaio($this->daMinhaTurma))->assertForbidden();

        foreach ([$this->secretaria, $this->criarUsuarioNaInstituicao($this->instituicao, 'admin')] as $usuario) {
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
        $usuarioDeFora = $this->criarUsuarioNaInstituicao($outra, 'professor');

        [$apresentacaoDeFora, $ensaioDeFora] = $this->naInstituicao($outra, function () use ($usuarioDeFora): array {
            $professorDeFora = Professor::create(['id_usuario' => $usuarioDeFora->id]);
            $this->curso = Curso::create(['nome' => 'Teatro', 'descricao' => 'Palco']);
            $this->nivel = Nivel::create(['nome' => 'Avançado']);
            $turma = $this->criarTurma($professorDeFora);
            $espetaculo = Espetaculo::create(['titulo' => 'Mostra', 'data_evento' => '2026-11-30', 'status' => 'ensaios']);
            $apresentacao = $this->apresentacao($espetaculo, $turma, 'Ato I', 1);
            $ensaio = Ensaio::create([
                'id_apresentacao' => $apresentacao->id,
                'id_professor' => $professorDeFora->id,
                'data' => '2026-10-10',
                'hora_inicio' => '10:00',
                'hora_termino' => '11:00',
            ]);

            return [$apresentacao, $ensaio];
        });

        $this->comoProfessor();
        $this->getJson(self::ROTA.'/'.$apresentacaoDeFora->id)->assertNotFound();
        $this->postJson('/api/ensaios', $this->dadosEnsaio($apresentacaoDeFora))->assertJsonValidationErrors('id_apresentacao');
        $this->putJson('/api/ensaios/'.$ensaioDeFora->id, ['data' => '2026-10-11', 'hora_inicio' => '10:00', 'hora_termino' => '11:00'])->assertNotFound();
        $this->deleteJson('/api/ensaios/'.$ensaioDeFora->id)->assertNotFound();
        $this->getJson('/api/ensaios')->assertOk()->assertJsonCount(0, 'data');

        $this->comoUsuario($usuarioDeFora, $outra);
        $this->getJson(self::ROTA)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/ensaios')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::ROTA.'/'.$this->daMinhaTurma->id)->assertNotFound();
    }
}
