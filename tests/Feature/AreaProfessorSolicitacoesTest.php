<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaPagamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

/**
 * Hoje é quinta, 15/10/2026. Paula leciona a Turma A (aula na terça 20/10, 14h–15h) e dá aulas
 * individuais à Ana (qua 21/10 18h com cobrança; qua 28/10 18h sem cobrança). Otto leciona a Turma Otto.
 */
class AreaProfessorSolicitacoesTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private const ROTA = '/api/professor/me/solicitacoes';

    private const ROTA_FILA = '/api/solicitacoes-aulas';

    private Instituicao $instituicao;

    private User $usuarioPaula;

    private User $usuarioOtto;

    private User $secretaria;

    private User $admin;

    private Professor $paula;

    private Professor $otto;

    private Turma $turmaA;

    private Turma $turmaOtto;

    private Curso $curso;

    private AulaTurma $aulaTurma;

    private AulaTurma $aulaAna;

    private AulaTurma $proximaAulaAna;

    private AulaTurma $aulaOtto;

    private Conta $cobrancaAna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 10:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);
        $this->usuarioOtto = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Otto Prof']);
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioAna): void {
            $this->curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);

            $this->paula = Professor::create(['id_usuario' => $this->usuarioPaula->id]);
            $this->otto = Professor::create(['id_usuario' => $this->usuarioOtto->id]);
            $this->turmaA = $this->criarTurma($this->paula, $nivel);
            $this->turmaOtto = $this->criarTurma($this->otto, $nivel);
            $ana = Aluno::create(['id_usuario' => $usuarioAna->id]);

            $this->aulaTurma = $this->aula(['id_turma' => $this->turmaA->id, 'id_professor' => $this->paula->id], '2026-10-20', '14:00', '15:00');
            $this->aulaOtto = $this->aula(['id_turma' => $this->turmaOtto->id, 'id_professor' => $this->otto->id], '2026-10-20', '14:00', '15:00');
            $individual = ['id_curso' => $this->curso->id, 'id_aluno_especifico' => $ana->id, 'id_professor' => $this->paula->id];
            $this->aulaAna = $this->aula($individual, '2026-10-21', '18:00', '19:00');
            $this->proximaAulaAna = $this->aula($individual, '2026-10-28', '18:00', '19:00');

            $this->cobrancaAna = Conta::create([
                'id_categoria' => CategoriaConta::firstOrCreate(['nome' => 'Aulas avulsas'], ['tipo' => 'receita'])->id,
                'id_aluno' => $ana->id,
                'id_aula_turma' => $this->aulaAna->id,
                'descricao' => 'Aula avulsa',
                'valor' => 80,
                'data_vencimento' => '2026-10-21',
                'status' => 'pendente',
                'tipo' => 'receita',
            ]);
        });
    }

    private function criarTurma(Professor $professor, Nivel $nivel): Turma
    {
        return Turma::create([
            'id_curso' => $this->curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'descricao' => 'Turma',
            'maximo_alunos' => 10,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
        ]);
    }

    private function aula(array $dados, string $data, string $inicio, string $termino): AulaTurma
    {
        return AulaTurma::create([
            'data' => $data,
            'hora_inicio' => $inicio,
            'hora_termino' => $termino,
            'status' => 'agendada',
            'tipo' => 'regular',
            ...$dados,
        ]);
    }

    private function permitir(array $modos): void
    {
        $this->instituicao->update(['permissoes_professor_aulas' => $modos + ['criar' => 'livre', 'editar' => 'livre', 'excluir' => 'livre']]);
    }

    private function solicitar(array $dados, ?User $usuario = null)
    {
        return $this->comoUsuario($usuario ?? $this->usuarioPaula, $this->instituicao)->postJson(self::ROTA, $dados);
    }

    private function decidir(int $id, array $dados)
    {
        return $this->comoUsuario($this->secretaria, $this->instituicao)->postJson(self::ROTA_FILA."/{$id}/decisao", $dados);
    }

    public function test_admin_configura_permissoes_e_professor_as_recebe_no_perfil(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson('/api/instituicao/permissoes-professor')
            ->assertOk()
            ->assertExactJson(['data' => ['criar' => 'livre', 'editar' => 'livre', 'excluir' => 'livre']]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->putJson('/api/instituicao/permissoes-professor', ['criar' => 'bloqueado', 'editar' => 'aprovacao', 'excluir' => 'aprovacao'])
            ->assertOk()
            ->assertJsonPath('data.criar', 'bloqueado');

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson('/api/professor/me')
            ->assertOk()
            ->assertJsonPath('data.permissoes_aulas.editar', 'aprovacao');

        $this->comoUsuario($this->admin, $this->instituicao)
            ->putJson('/api/instituicao/permissoes-professor', ['criar' => 'talvez', 'editar' => 'livre', 'excluir' => 'livre'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('criar');

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->putJson('/api/instituicao/permissoes-professor', ['criar' => 'livre', 'editar' => 'livre', 'excluir' => 'livre'])
            ->assertForbidden();
    }

    public function test_com_aprovacao_a_edicao_direta_e_negada_e_o_cancelamento_vira_pendente_ate_a_secretaria_aprovar(): void
    {
        $this->permitir(['excluir' => 'aprovacao', 'editar' => 'aprovacao']);

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->deleteJson("/api/aulas-turmas/{$this->aulaTurma->id}")
            ->assertForbidden();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->putJson("/api/aulas-turmas/{$this->aulaTurma->id}", ['hora_inicio' => '15:00', 'hora_termino' => '16:00'])
            ->assertForbidden();

        $id = $this->solicitar(['tipo' => 'cancelamento', 'id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'Consulta médica'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pendente')
            ->assertJsonPath('data.aplicada_automaticamente', false)
            ->assertJsonPath('data.pode_cancelar', true)
            ->json('data.id');

        $this->assertSame('agendada', $this->aulaTurma->fresh()->status);

        $this->solicitar(['tipo' => 'reposicao', 'id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'Outra', 'data_sugerida' => '2026-10-27', 'hora_inicio_sugerida' => '14:00', 'hora_termino_sugerida' => '15:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_aula_turma');

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->getJson(self::ROTA_FILA)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.professor.nome', 'Paula Prof')
            ->assertJsonPath('data.0.analise.tem_cobranca', false);

        $this->decidir($id, ['decisao' => 'rejeitada'])->assertUnprocessable()->assertJsonValidationErrors('motivo_decisao');

        $this->decidir($id, ['decisao' => 'aprovada'])
            ->assertOk()
            ->assertJsonPath('data.status', 'aprovada')
            ->assertJsonPath('data.decisor.nome', $this->secretaria->nome);

        $this->assertSame('cancelada', $this->aulaTurma->fresh()->status);
        $this->decidir($id, ['decisao' => 'aprovada'])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_com_acao_livre_a_reposicao_e_aplicada_na_hora(): void
    {
        $resposta = $this->solicitar([
            'tipo' => 'reposicao',
            'id_aula_turma' => $this->aulaTurma->id,
            'motivo' => 'Feriado municipal',
            'data_sugerida' => '2026-10-27',
            'hora_inicio_sugerida' => '16:00',
            'hora_termino_sugerida' => '17:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'aprovada')
            ->assertJsonPath('data.aplicada_automaticamente', true)
            ->assertJsonPath('data.aula_gerada.data', '2026-10-27')
            ->assertJsonPath('data.aula_gerada.hora_inicio', '16:00');

        $nova = AulaTurma::withoutGlobalScopes()->findOrFail($resposta->json('data.aula_gerada.id'));
        $this->assertSame('reposicao', $nova->tipo);
        $this->assertSame($this->turmaA->id, $nova->id_turma);
        $this->assertSame($this->paula->id, (int) $nova->id_professor);
        $this->assertSame('cancelada', $this->aulaTurma->fresh()->status);
    }

    public function test_acao_bloqueada_retorna_403_no_pedido_e_na_rota_direta(): void
    {
        $this->permitir(['editar' => 'bloqueado', 'criar' => 'bloqueado']);

        $this->solicitar(['tipo' => 'substituicao', 'id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'Viagem', 'id_professor_substituto' => $this->otto->id])
            ->assertForbidden()
            ->assertJsonPath('message', 'A escola não permite que professores alterem aulas.');

        $this->solicitar(['tipo' => 'criacao', 'id_turma' => $this->turmaA->id, 'data_sugerida' => '2026-10-23', 'hora_inicio_sugerida' => '10:00', 'hora_termino_sugerida' => '11:00'])
            ->assertForbidden();

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->postJson('/api/aulas-turmas', [
                'id_turma' => $this->turmaA->id,
                'id_professor' => $this->paula->id,
                'data' => '2026-10-23',
                'hora_inicio' => '10:00',
                'hora_termino' => '11:00',
            ])
            ->assertForbidden();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->putJson("/api/aulas-turmas/{$this->aulaTurma->id}", ['hora_inicio' => '15:00', 'hora_termino' => '16:00'])
            ->assertForbidden();

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->putJson("/api/aulas-turmas/{$this->aulaTurma->id}", ['hora_inicio' => '15:00', 'hora_termino' => '16:00'])
            ->assertOk();
    }

    public function test_criacao_livre_agenda_aula_extra_na_turma_do_professor(): void
    {
        $resposta = $this->solicitar(['tipo' => 'criacao', 'id_turma' => $this->turmaA->id, 'tipo_aula' => 'reforco', 'data_sugerida' => '2026-10-23', 'hora_inicio_sugerida' => '10:00', 'hora_termino_sugerida' => '11:00'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'aprovada')
            ->assertJsonPath('data.nova_aula.tipo_aula', 'reforco');

        $aula = AulaTurma::withoutGlobalScopes()->findOrFail($resposta->json('data.aula_gerada.id'));
        $this->assertSame('reforco', $aula->tipo);
        $this->assertSame($this->curso->id, (int) $aula->id_curso);

        $this->solicitar(['tipo' => 'criacao', 'id_turma' => $this->turmaOtto->id, 'data_sugerida' => '2026-10-23', 'hora_inicio_sugerida' => '12:00', 'hora_termino_sugerida' => '13:00'])
            ->assertForbidden();
    }

    public function test_conflito_de_agenda_impede_e_horario_fora_da_disponibilidade_so_avisa(): void
    {
        $this->permitir(['editar' => 'aprovacao']);

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->putJson('/api/professor/me/disponibilidades', ['disponibilidades' => [
                ['dia_semana' => 'segunda', 'hora_inicio' => '08:00', 'hora_termino' => '12:00'],
            ]])
            ->assertOk();

        $this->solicitar(['tipo' => 'reposicao', 'id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'Viagem', 'data_sugerida' => '2026-10-21', 'hora_inicio_sugerida' => '18:30', 'hora_termino_sugerida' => '19:30'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data_sugerida');

        $id = $this->solicitar(['tipo' => 'reposicao', 'id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'Viagem', 'data_sugerida' => '2026-10-23', 'hora_inicio_sugerida' => '14:00', 'hora_termino_sugerida' => '15:00'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pendente')
            ->assertJsonPath('data.analise.avisos.0', 'O horário fica fora da disponibilidade cadastrada pelo professor.')
            ->assertJsonPath('data.analise.disponibilidade.0.dia_semana', 'segunda')
            ->json('data.id');

        $this->decidir($id, ['decisao' => 'aprovada', 'data_sugerida' => '2026-10-21', 'hora_inicio_sugerida' => '18:00', 'hora_termino_sugerida' => '19:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data_sugerida');

        $resposta = $this->decidir($id, ['decisao' => 'aprovada', 'data_sugerida' => '2026-10-26', 'hora_inicio_sugerida' => '09:00', 'hora_termino_sugerida' => '10:00'])
            ->assertOk()
            ->assertJsonPath('data.aula_gerada.data', '2026-10-26')
            ->assertJsonPath('data.aula_gerada.hora_inicio', '09:00');

        $this->assertSame('reposicao', AulaTurma::withoutGlobalScopes()->find($resposta->json('data.aula_gerada.id'))->tipo);
    }

    public function test_secretaria_escolhe_o_substituto_que_passa_a_fazer_a_chamada(): void
    {
        $this->permitir(['editar' => 'aprovacao']);
        $usuarioLia = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Lia Prof']);
        $lia = $this->naInstituicao($this->instituicao, fn () => Professor::create(['id_usuario' => $usuarioLia->id]));

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson("/api/agenda/professores-livres?data=2026-10-20&hora_inicio=14:00&hora_termino=15:00&ignorar_aula={$this->aulaTurma->id}")
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Lia Prof')
            ->assertJsonPath('data.0.conflito', false)
            ->assertJsonFragment(['nome' => 'Otto Prof', 'conflito' => true]);

        $id = $this->solicitar(['tipo' => 'substituicao', 'id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'Congresso'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pendente')
            ->json('data.id');

        $this->decidir($id, ['decisao' => 'aprovada'])->assertUnprocessable()->assertJsonValidationErrors('id_professor_substituto');
        $this->decidir($id, ['decisao' => 'aprovada', 'id_professor_substituto' => $this->otto->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_professor_substituto');

        $this->decidir($id, ['decisao' => 'aprovada', 'id_professor_substituto' => $lia->id])
            ->assertOk()
            ->assertJsonPath('data.substituto.nome', 'Lia Prof');

        $this->assertSame($lia->id, (int) $this->aulaTurma->fresh()->id_professor);
        $this->comoUsuario($usuarioLia, $this->instituicao)
            ->getJson("/api/professor/me/aulas/{$this->aulaTurma->id}")
            ->assertOk();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson("/api/professor/me/aulas/{$this->aulaTurma->id}")
            ->assertOk();
    }

    public function test_cobranca_da_aula_cancelada_vai_para_a_proxima_aula_do_aluno(): void
    {
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson("/api/professor/me/aulas/{$this->aulaAna->id}/opcoes-solicitacao")
            ->assertOk()
            ->assertJsonPath('data.tem_cobranca', true)
            ->assertJsonPath('data.proxima_aula_cobranca', 'Aula individual de Ana Lima em 28/10, 18:00–19:00')
            ->assertJsonPath('data.permissoes.excluir', 'livre');

        $this->solicitar(['tipo' => 'cancelamento', 'id_aula_turma' => $this->aulaAna->id, 'motivo' => 'Doente'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destino_cobranca');

        $this->solicitar(['tipo' => 'cancelamento', 'id_aula_turma' => $this->aulaAna->id, 'motivo' => 'Doente', 'destino_cobranca' => 'proxima_aula'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'aprovada');

        $conta = Conta::withoutGlobalScopes()->find($this->cobrancaAna->id);
        $this->assertSame($this->proximaAulaAna->id, (int) $conta->id_aula_turma);
        $this->assertSame('pendente', $conta->status);
        $this->assertStringContainsString('transferida para a aula de 28/10/2026', $conta->observacoes);
        $this->assertSame('cancelada', $this->aulaAna->fresh()->status);
    }

    public function test_cobranca_cancelada_junto_com_a_aula_e_bloqueada_se_ja_paga(): void
    {
        ContaPagamento::create(['id_conta' => $this->cobrancaAna->id, 'valor_pago' => 80, 'data_pagamento' => '2026-10-10', 'forma_pagamento' => 'pix']);

        $this->solicitar(['tipo' => 'cancelamento', 'id_aula_turma' => $this->aulaAna->id, 'motivo' => 'Doente', 'destino_cobranca' => 'cancelar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('destino_cobranca');
        $this->assertSame('agendada', $this->aulaAna->fresh()->status);

        ContaPagamento::query()->delete();

        $this->solicitar(['tipo' => 'cancelamento', 'id_aula_turma' => $this->aulaAna->id, 'motivo' => 'Doente', 'destino_cobranca' => 'cancelar'])
            ->assertCreated();

        $this->assertSame('cancelado', Conta::withoutGlobalScopes()->find($this->cobrancaAna->id)->status);
    }

    public function test_cobranca_da_reposicao_acompanha_a_nova_aula(): void
    {
        $this->permitir(['editar' => 'aprovacao']);

        $id = $this->solicitar(['tipo' => 'reposicao', 'id_aula_turma' => $this->aulaAna->id, 'motivo' => 'Viagem', 'data_sugerida' => '2026-10-22', 'hora_inicio_sugerida' => '18:00', 'hora_termino_sugerida' => '19:00', 'destino_cobranca' => 'cancelar'])
            ->assertCreated()
            ->assertJsonPath('data.analise.tem_cobranca', true)
            ->json('data.id');

        $nova = $this->decidir($id, ['decisao' => 'aprovada', 'destino_cobranca' => 'proxima_aula'])
            ->assertOk()
            ->assertJsonPath('data.destino_cobranca', 'proxima_aula')
            ->json('data.aula_gerada.id');

        $this->assertSame($nova, (int) Conta::withoutGlobalScopes()->find($this->cobrancaAna->id)->id_aula_turma);
    }

    public function test_professor_cancela_somente_a_propria_solicitacao_pendente(): void
    {
        $this->permitir(['excluir' => 'aprovacao']);
        $id = $this->solicitar(['tipo' => 'cancelamento', 'id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'Viagem'])->json('data.id');

        $this->comoUsuario($this->usuarioOtto, $this->instituicao)->deleteJson(self::ROTA."/{$id}")->assertForbidden();
        $this->comoUsuario($this->usuarioOtto, $this->instituicao)->getJson(self::ROTA)->assertOk()->assertJsonCount(0, 'data');

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->getJson(self::ROTA)->assertOk()->assertJsonCount(1, 'data');
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->deleteJson(self::ROTA."/{$id}")->assertNoContent();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->deleteJson(self::ROTA."/{$id}")->assertUnprocessable();

        $this->assertSame(SolicitacaoAula::STATUS_CANCELADA, SolicitacaoAula::withoutGlobalScopes()->find($id)->status);
    }

    public function test_disponibilidade_rejeita_janelas_sobrepostas_e_secretaria_consulta(): void
    {
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->putJson('/api/professor/me/disponibilidades', ['disponibilidades' => [
                ['dia_semana' => 'terca', 'hora_inicio' => '08:00', 'hora_termino' => '12:00'],
                ['dia_semana' => 'terca', 'hora_inicio' => '11:00', 'hora_termino' => '13:00'],
            ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('disponibilidades.1.hora_inicio');

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->putJson('/api/professor/me/disponibilidades', ['disponibilidades' => [
                ['dia_semana' => 'sexta', 'hora_inicio' => '14:00', 'hora_termino' => '18:00'],
                ['dia_semana' => 'segunda', 'hora_inicio' => '08:00', 'hora_termino' => '12:00'],
            ]])
            ->assertOk()
            ->assertJsonPath('data.0.dia_semana', 'segunda')
            ->assertJsonPath('data.1.hora_termino', '18:00');

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->getJson("/api/professores/{$this->paula->id}/disponibilidades")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->putJson('/api/professor/me/disponibilidades', ['disponibilidades' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_acessos_por_papel(): void
    {
        $this->getJson(self::ROTA)->assertUnauthorized();
        $this->getJson(self::ROTA_FILA)->assertUnauthorized();
        $this->getJson('/api/professor/me/disponibilidades')->assertUnauthorized();

        $aluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $this->comoUsuario($aluno, $this->instituicao)->getJson(self::ROTA)->assertForbidden();
        $this->comoUsuario($aluno, $this->instituicao)->getJson('/api/agenda/professores-livres?data=2026-10-20&hora_inicio=14:00&hora_termino=15:00')->assertForbidden();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->getJson(self::ROTA_FILA)->assertForbidden();
        $this->comoUsuario($this->secretaria, $this->instituicao)->postJson(self::ROTA, ['tipo' => 'cancelamento'])->assertForbidden();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)->getJson('/api/instituicao/permissoes-professor')->assertForbidden();

        $this->solicitar(['tipo' => 'cancelamento', 'id_aula_turma' => $this->aulaOtto->id, 'motivo' => 'Teste'])->assertForbidden();
        $this->comoUsuario($this->usuarioPaula, $this->instituicao)
            ->getJson("/api/professor/me/aulas/{$this->aulaOtto->id}/opcoes-solicitacao")
            ->assertForbidden();
    }

    public function test_isolamento_entre_instituicoes(): void
    {
        $this->permitir(['excluir' => 'aprovacao']);
        $id = $this->solicitar(['tipo' => 'cancelamento', 'id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'Viagem'])->json('data.id');

        $outra = Instituicao::create(['slug' => 'outra-escola', 'nome_fantasia' => 'Outra Escola', 'status' => Instituicao::STATUS_ATIVO]);
        $secretariaFora = $this->criarUsuarioNaInstituicao($outra, 'secretaria');
        $professorFora = $this->criarUsuarioNaInstituicao($outra, 'professor');
        $this->naInstituicao($outra, fn () => Professor::create(['id_usuario' => $professorFora->id]));

        $this->comoUsuario($secretariaFora, $outra)->getJson(self::ROTA_FILA)->assertOk()->assertJsonCount(0, 'data');
        $this->comoUsuario($secretariaFora, $outra)->postJson(self::ROTA_FILA."/{$id}/decisao", ['decisao' => 'aprovada'])->assertNotFound();
        $this->comoUsuario($secretariaFora, $outra)->getJson("/api/professores/{$this->paula->id}/disponibilidades")->assertNotFound();

        $this->comoUsuario($professorFora, $outra)
            ->postJson(self::ROTA, ['tipo' => 'cancelamento', 'id_aula_turma' => $this->aulaTurma->id, 'motivo' => 'X'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_aula_turma');
        $this->comoUsuario($professorFora, $outra)->deleteJson(self::ROTA."/{$id}")->assertNotFound();

        $this->assertSame('pendente', SolicitacaoAula::withoutGlobalScopes()->find($id)->status);
    }
}
