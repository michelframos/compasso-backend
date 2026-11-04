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
use App\Modules\Academico\Models\SolicitacaoReposicao;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoReposicoesTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private User $usuarioPaula;

    private User $usuarioCarlos;

    private Aluno $ana;

    private Aluno $bruno;

    private Professor $paula;

    private AulaTurma $falta;

    private AulaTurma $outraFalta;

    private AulaTurma $faltaAntiga;

    private AulaTurma $maisUma;

    private AulaTurma $presente;

    private AulaTurma $cancelada;

    private AulaTurma $destino;

    private AulaTurma $conflito;

    private AulaTurma $lotada;

    private AulaTurma $doBruno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->instituicao->update([
            'limite_reposicoes_periodo' => 2,
            'prazo_reposicao_dias' => 30,
        ]);

        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $this->usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);
        $this->usuarioCarlos = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Carlos Prof']);

        $usuarioLotado = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Lota Aluno']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno, $usuarioLotado): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $this->paula = Professor::create(['id_usuario' => $this->usuarioPaula->id]);
            $carlos = Professor::create(['id_usuario' => $this->usuarioCarlos->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $lotado = Aluno::create(['id_usuario' => $usuarioLotado->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $turmaAna = $this->turma($curso, $nivel, $this->paula, 'Turma Ana', 10);
            $turmaBruno = $this->turma($curso, $nivel, $carlos, 'Turma Bruno', 2);
            $turmaLotada = $this->turma($curso, $nivel, $carlos, 'Turma Lotada', 1);
            $this->matricular($this->ana, $turmaAna);
            $this->matricular($this->bruno, $turmaBruno);
            $this->matricular($lotado, $turmaLotada);

            $this->falta = $this->aula($turmaAna, $this->paula, '2026-10-20', 'concluida', '14:00', '15:00', 'segredo da chamada');
            $this->outraFalta = $this->aula($turmaAna, $this->paula, '2026-10-21', 'concluida', '14:00', '15:00');
            $this->faltaAntiga = $this->aula($turmaAna, $this->paula, '2026-09-01', 'concluida', '14:00', '15:00');
            $this->maisUma = $this->aula($turmaAna, $this->paula, '2026-10-19', 'concluida', '14:00', '15:00');
            $this->presente = $this->aula($turmaAna, $this->paula, '2026-10-22', 'concluida', '14:00', '15:00');
            $this->cancelada = $this->aula($turmaAna, $this->paula, '2026-10-23', 'cancelada', '14:00', '15:00');
            $this->aula($turmaAna, $this->paula, '2026-10-28', 'agendada', '14:00', '15:00');
            $this->destino = $this->aula($turmaBruno, $carlos, '2026-10-28', 'agendada', '16:00', '17:00');
            $this->conflito = $this->aula($turmaBruno, $carlos, '2026-10-28', 'agendada', '14:00', '15:00');
            $this->lotada = $this->aula($turmaLotada, $carlos, '2026-10-29', 'agendada', '16:00', '17:00');
            $this->doBruno = $this->aula($turmaBruno, $carlos, '2026-10-15', 'concluida', '14:00', '15:00');

            AulaPresenca::create(['id_aula_turma' => $this->falta->id, 'id_aluno' => $this->ana->id, 'status' => 'ausente']);
            AulaPresenca::create(['id_aula_turma' => $this->outraFalta->id, 'id_aluno' => $this->ana->id, 'status' => 'ausente']);
            AulaPresenca::create(['id_aula_turma' => $this->faltaAntiga->id, 'id_aluno' => $this->ana->id, 'status' => 'ausente']);
            AulaPresenca::create(['id_aula_turma' => $this->maisUma->id, 'id_aluno' => $this->ana->id, 'status' => 'ausente']);
            AulaPresenca::create(['id_aula_turma' => $this->presente->id, 'id_aluno' => $this->ana->id, 'status' => 'presente']);
            AulaPresenca::create(['id_aula_turma' => $this->doBruno->id, 'id_aluno' => $this->bruno->id, 'status' => 'ausente']);
            SolicitacaoReposicao::create([
                'id_aluno' => $this->bruno->id,
                'id_aula_turma_perdida' => $this->doBruno->id,
                'motivo' => 'Pedido do Bruno',
                'status' => 'pendente',
            ]);
        });
    }

    public function test_aluno_pede_reposicao_respeitando_prazo_vaga_e_horario(): void
    {
        $opcoes = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/reposicoes/opcoes?id_aula_turma_perdida='.$this->falta->id)
            ->assertOk()
            ->json('data');

        $this->assertEqualsCanonicalizing([$this->destino->id], collect($opcoes)->pluck('id')->all());
        $this->assertStringNotContainsString('segredo da chamada', json_encode($opcoes));

        $pedido = $this->postJson('/api/aluno/me/reposicoes', [
            'id_aula_turma_perdida' => $this->falta->id,
            'id_aula_turma_destino' => $this->destino->id,
            'motivo' => 'Quero repor',
        ])->assertCreated()->json('data');

        $this->assertSame('pendente', $pedido['status']);
        $this->assertSame($this->destino->id, $pedido['id_aula_turma_destino']);

        $this->postJson('/api/aluno/me/reposicoes', [
            'id_aula_turma_perdida' => $this->falta->id,
            'motivo' => 'De novo',
        ])->assertStatus(422)->assertJsonValidationErrors('id_aula_turma_perdida');

        $this->postJson('/api/aluno/me/reposicoes', [
            'id_aula_turma_perdida' => $this->outraFalta->id,
            'id_aula_turma_destino' => $this->conflito->id,
            'motivo' => 'Mesmo horário',
        ])->assertStatus(422)->assertJsonValidationErrors('id_aula_turma_destino');

        $this->postJson('/api/aluno/me/reposicoes', [
            'id_aula_turma_perdida' => $this->outraFalta->id,
            'id_aula_turma_destino' => $this->lotada->id,
            'motivo' => 'Sem vaga',
        ])->assertStatus(422)->assertJsonValidationErrors('id_aula_turma_destino');

        $this->postJson('/api/aluno/me/reposicoes', [
            'id_aula_turma_perdida' => $this->faltaAntiga->id,
            'motivo' => 'Prazo estourado',
        ])->assertStatus(422)->assertJsonValidationErrors('id_aula_turma_perdida');

        $this->postJson('/api/aluno/me/reposicoes', [
            'id_aula_turma_perdida' => $this->presente->id,
            'motivo' => 'Não faltei',
        ])->assertStatus(422)->assertJsonValidationErrors('id_aula_turma_perdida');

        $this->postJson('/api/aluno/me/reposicoes', [
            'id_aula_turma_perdida' => $this->cancelada->id,
            'motivo' => 'Cancelada',
        ])->assertStatus(422)->assertJsonValidationErrors('id_aula_turma_perdida');

        $this->postJson('/api/aluno/me/reposicoes', [
            'id_aula_turma_perdida' => $this->doBruno->id,
            'motivo' => 'Aula do Bruno',
        ])->assertForbidden();

        $lista = $this->getJson('/api/aluno/me/reposicoes')->assertOk();
        $lista->assertJsonPath('regras.limite_periodo', 2);
        $lista->assertJsonPath('regras.prazo_dias', 30);
        $lista->assertJsonPath('regras.usados_no_periodo', 1);
        $this->assertStringNotContainsString('Pedido do Bruno', json_encode($lista->json('data')));
        $this->assertStringNotContainsString('Bruno Melo', json_encode($lista->json('data')));

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->postJson('/api/aluno/me/reposicoes', [
                'id_aula_turma_perdida' => $this->outraFalta->id,
                'motivo' => 'Sem o aluno',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $this->postJson('/api/aluno/me/reposicoes?id_aluno='.$this->ana->id, [
            'id_aula_turma_perdida' => $this->outraFalta->id,
            'motivo' => 'A Rita pediu',
        ])->assertCreated();

        $this->postJson('/api/aluno/me/reposicoes?id_aluno='.$this->bruno->id, [
            'id_aula_turma_perdida' => $this->doBruno->id,
            'motivo' => 'Sem vínculo',
        ])->assertForbidden();

        $this->postJson('/api/aluno/me/reposicoes?id_aluno='.$this->ana->id, [
            'id_aula_turma_perdida' => $this->maisUma->id,
            'motivo' => 'Terceiro pedido',
        ])->assertStatus(422)->assertJsonValidationErrors('id_aula_turma_perdida');
    }

    public function test_secretaria_e_professor_aprovam_e_a_presenca_entra_na_aula_de_destino(): void
    {
        $comDestino = $this->pedido($this->falta, $this->destino, 'Quero repor');
        $semDestino = $this->pedido($this->outraFalta, null, 'A escola escolhe');

        $daPaula = $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson('/api/solicitacoes-reposicoes')
            ->assertOk()
            ->json('data');

        $this->assertEqualsCanonicalizing(
            ['Quero repor', 'A escola escolhe'],
            collect($daPaula)->pluck('motivo')->all()
        );
        $this->assertStringNotContainsString('Pedido do Bruno', json_encode($daPaula));

        $doCarlos = $this->comoUsuario($this->usuarioCarlos, $this->instituicao)
            ->getJson('/api/solicitacoes-reposicoes')
            ->assertOk()
            ->json('data');

        $this->assertEqualsCanonicalizing(['Quero repor', 'Pedido do Bruno'], collect($doCarlos)->pluck('motivo')->all());

        $terceiro = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Terceiro Prof']);
        $this->naInstituicao($this->instituicao, fn () => Professor::create(['id_usuario' => $terceiro->id]));
        $this->comoUsuario($terceiro, $this->instituicao)
            ->postJson('/api/solicitacoes-reposicoes/'.$comDestino->id.'/decisao', ['decisao' => 'aprovada'])
            ->assertForbidden();

        $this->comoUsuario($this->usuarioCarlos, $this->instituicao)
            ->postJson('/api/solicitacoes-reposicoes/'.$comDestino->id.'/decisao', ['decisao' => 'aprovada'])
            ->assertOk()
            ->assertJsonPath('data.status', 'aprovada');

        $this->assertSame('presente', AulaPresenca::query()->where('id_aula_turma', $this->destino->id)->where('id_aluno', $this->ana->id)->value('status'));
        $this->assertSame('Quero repor', AulaPresenca::query()->where('id_aula_turma', $this->destino->id)->value('observacao'));

        $this->postJson('/api/solicitacoes-reposicoes/'.$comDestino->id.'/decisao', ['decisao' => 'recusada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $opcoes = $this->comoUsuario($secretaria, $this->instituicao)
            ->getJson('/api/solicitacoes-reposicoes/'.$semDestino->id.'/opcoes')
            ->assertOk()
            ->json('data');
        $this->assertContains($this->destino->id, collect($opcoes)->pluck('id')->all());

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/solicitacoes-reposicoes/'.$semDestino->id.'/decisao', ['decisao' => 'aprovada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aula_turma_destino');

        $this->postJson('/api/solicitacoes-reposicoes/'.$semDestino->id.'/decisao', [
            'decisao' => 'aprovada',
            'id_aula_turma_destino' => $this->destino->id,
        ])->assertOk()->assertJsonPath('data.status', 'aprovada');

        $this->assertSame(1, AulaPresenca::query()->where('id_aula_turma', $this->destino->id)->where('id_aluno', $this->ana->id)->count());
    }

    public function test_recusa_nao_cria_presenca_e_os_demais_papeis_recebem_401_ou_403(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/reposicoes')
            ->assertUnauthorized();

        $pedido = $this->pedido($this->falta, $this->destino, 'Quero repor');
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/solicitacoes-reposicoes/'.$pedido->id.'/decisao', ['decisao' => 'recusada'])
            ->assertOk()
            ->assertJsonPath('data.status', 'recusada');

        $this->assertNull(AulaPresenca::query()->where('id_aula_turma', $this->destino->id)->where('id_aluno', $this->ana->id)->value('status'));

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/reposicoes')
                ->assertForbidden();
        }

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/solicitacoes-reposicoes')
            ->assertForbidden();

        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'aluno', ['nome' => 'Aluno de Fora']);
        $this->naInstituicao($outra, fn () => Aluno::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/aluno/me/reposicoes')
            ->assertForbidden();
    }

    private function pedido(AulaTurma $perdida, ?AulaTurma $destino, string $motivo): SolicitacaoReposicao
    {
        return $this->naInstituicao($this->instituicao, fn () => SolicitacaoReposicao::create([
            'id_aluno' => $this->ana->id,
            'id_aula_turma_perdida' => $perdida->id,
            'id_aula_turma_destino' => $destino?->id,
            'motivo' => $motivo,
            'status' => 'pendente',
        ]));
    }

    private function turma(Curso $curso, Nivel $nivel, Professor $professor, string $descricao, int $vagas): Turma
    {
        return Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'descricao' => $descricao,
            'maximo_alunos' => $vagas,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
            'valor_mensalidade' => 350,
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

    private function aula(
        Turma $turma,
        Professor $professor,
        string $data,
        string $status,
        string $inicio,
        string $termino,
        ?string $conteudo = null,
    ): AulaTurma {
        return AulaTurma::create([
            'id_turma' => $turma->id,
            'id_curso' => $turma->id_curso,
            'id_professor' => $professor->id,
            'data' => $data,
            'hora_inicio' => $inicio,
            'hora_termino' => $termino,
            'status' => $status,
            'tipo' => 'regular',
            'conteudo_dado' => $conteudo,
        ]);
    }
}
