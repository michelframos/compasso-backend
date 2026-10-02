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
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Services\AulaSnapshotService;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaPagamento;
use App\Modules\Relatorios\Models\FechamentoProfessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

/**
 * Cenário de setembro/2026 da professora Paula (fixo 1000, hora 40, comissão 10%):
 * - Turma A (hora 60, comissão 20%): aula 1h30 sem snapshot (90) e aula 1h com snapshot de 50.
 * - Turma B (sem regras próprias): aula de 2h (80), mais uma agendada e uma cancelada.
 * - Aula individual de 1h (40). Aula da Turma A dada pelo Otto (substituto) não conta.
 * - Pagos em setembro: Ana na Turma A 300 (60), Bruno na Turma B 100 de 200 (10),
 *   Ana individual 400 + aula avulsa 50 (45). Carla, da turma do Otto, não conta.
 * Total: 1000 + 260 + 115 = 1375.
 */
class AreaProfessorGanhosTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private const ROTA = '/api/professor/me/extrato';

    private const ROTA_ADMIN = '/api/relatorios/professores';

    private Instituicao $instituicao;

    private User $usuarioProfessor;

    private User $admin;

    private User $secretaria;

    private Professor $professor;

    private Professor $outroProfessor;

    private Turma $turmaA;

    private Turma $turmaB;

    private Curso $curso;

    private Nivel $nivel;

    private Aluno $ana;

    private Conta $mensalidadeAna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 10:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $usuarioOtto = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Otto Prof']);
        $usuarios = collect(['Ana Lima', 'Bruno Melo', 'Carla Dias'])
            ->map(fn (string $nome) => $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => $nome]));

        $this->naInstituicao($this->instituicao, function () use ($usuarioOtto, $usuarios): void {
            $this->curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $this->nivel = Nivel::create(['nome' => 'Básico']);

            $this->professor = Professor::create([
                'id_usuario' => $this->usuarioProfessor->id,
                'salario_fixo' => 1000,
                'valor_hora_aula' => 40,
                'comissao' => 10,
            ]);
            $this->outroProfessor = Professor::create(['id_usuario' => $usuarioOtto->id, 'valor_hora_aula' => 30, 'comissao' => 15]);

            $this->turmaA = $this->criarTurma($this->professor, 'Turma A', ['valor_hora_aula_especifico' => 60, 'percentual_comissao_especifico' => 20]);
            $this->turmaB = $this->criarTurma($this->professor, 'Turma B');
            $turmaOtto = $this->criarTurma($this->outroProfessor, 'Turma Otto');

            $this->ana = Aluno::create(['id_usuario' => $usuarios[0]->id]);
            $bruno = Aluno::create(['id_usuario' => $usuarios[1]->id]);
            $carla = Aluno::create(['id_usuario' => $usuarios[2]->id]);

            $this->aula($this->turmaA, '2026-09-10', '14:00', '15:30');
            $this->aula($this->turmaA, '2026-09-11', '14:00', '15:00', ['valor_hora_aula_aplicado' => 50]);
            $this->aula($this->turmaB, '2026-09-12', '10:00', '12:00');
            $this->aula($this->turmaB, '2026-09-19', '10:00', '12:00', ['status' => 'agendada']);
            $this->aula($this->turmaB, '2026-09-26', '10:00', '12:00', ['status' => 'cancelada']);
            $individual = $this->aula(null, '2026-09-20', '18:00', '19:00', [
                'id_professor' => $this->professor->id,
                'id_curso' => $this->curso->id,
                'id_aluno_especifico' => $this->ana->id,
            ]);
            $this->aula($this->turmaA, '2026-09-25', '14:00', '15:00', ['id_professor' => $this->outroProfessor->id]);
            $this->aula($this->turmaA, '2026-10-01', '14:00', '15:00');

            $this->mensalidadeAna = $this->conta($this->ana, 300, ['id_matricula' => $this->matricular($this->ana, $this->turmaA)->id]);
            $this->pagar($this->mensalidadeAna, 300, '2026-09-05');

            $contaBruno = $this->conta($bruno, 200, ['id_matricula' => $this->matricular($bruno, $this->turmaB)->id]);
            $this->pagar($contaBruno, 100, '2026-09-08');
            $this->pagar($contaBruno, 100, '2026-10-02');

            $this->conta($bruno, 200, ['id_matricula' => $this->matricular($bruno, $this->turmaA)->id]);

            $contaCarla = $this->conta($carla, 300, ['id_matricula' => $this->matricular($carla, $turmaOtto)->id]);
            $this->pagar($contaCarla, 300, '2026-09-05');

            $individualAna = Matricula::create([
                'id_aluno' => $this->ana->id,
                'tipo' => 'curso',
                'id_curso' => $this->curso->id,
                'id_nivel' => $this->nivel->id,
                'id_professor' => $this->professor->id,
                'data' => '2026-09-01',
                'status' => 'ativa',
            ]);
            $this->pagar($this->conta($this->ana, 400, ['id_matricula' => $individualAna->id]), 400, '2026-09-15');
            $this->pagar($this->conta($this->ana, 50, ['id_aula_turma' => $individual->id]), 50, '2026-09-20');
        });
    }

    private function criarTurma(Professor $professor, string $descricao, array $dados = []): Turma
    {
        return Turma::create([
            'id_curso' => $this->curso->id,
            'id_nivel' => $this->nivel->id,
            'id_professor' => $professor->id,
            'descricao' => $descricao,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
            'valor_mensalidade' => 300,
            ...$dados,
        ]);
    }

    private function aula(?Turma $turma, string $data, string $inicio, string $termino, array $dados = []): AulaTurma
    {
        return AulaTurma::create([
            'id_turma' => $turma?->id,
            'id_curso' => $turma?->id_curso,
            'id_nivel' => $turma?->id_nivel,
            'id_professor' => $turma?->id_professor,
            'data' => $data,
            'hora_inicio' => $inicio,
            'hora_termino' => $termino,
            'status' => 'concluida',
            'tipo' => 'regular',
            ...$dados,
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

    private function conta(Aluno $aluno, float $valor, array $dados): Conta
    {
        $categoria = CategoriaConta::firstOrCreate(['nome' => 'Mensalidades'], ['tipo' => 'receita']);

        return Conta::create([
            'id_categoria' => $categoria->id,
            'id_aluno' => $aluno->id,
            'descricao' => 'Mensalidade',
            'valor' => $valor,
            'data_vencimento' => '2026-09-10',
            'status' => 'pendente',
            'tipo' => 'receita',
            ...$dados,
        ]);
    }

    private function pagar(Conta $conta, float $valor, string $data): void
    {
        ContaPagamento::create([
            'id_conta' => $conta->id,
            'valor_pago' => $valor,
            'data_pagamento' => $data,
            'forma_pagamento' => 'pix',
        ]);
    }

    private function fecharSetembro(array $dados = [])
    {
        return $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson(self::ROTA_ADMIN."/{$this->professor->id}/fechamentos", ['mes' => '2026-09', ...$dados]);
    }

    public function test_professor_ve_fixo_hora_aula_por_duracao_e_comissao_sobre_o_pago_no_mes(): void
    {
        $resposta = $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson(self::ROTA.'?mes=2026-09')
            ->assertOk()
            ->assertJsonPath('data.competencia', '2026-09')
            ->assertJsonPath('data.situacao', 'aberto')
            ->assertJsonPath('data.professor.nome', 'Paula Prof')
            ->assertJsonPath('data.resumo.salario_fixo', 1000)
            ->assertJsonPath('data.resumo.total_aulas', 4)
            ->assertJsonPath('data.resumo.total_horas', 5.5)
            ->assertJsonPath('data.resumo.valor_hora_aula', 260)
            ->assertJsonPath('data.resumo.base_comissao', 850)
            ->assertJsonPath('data.resumo.valor_comissao', 115)
            ->assertJsonPath('data.resumo.valor_total', 1375)
            ->assertJsonPath('data.fechamento', null);

        $aulas = collect($resposta->json('data.aulas'));
        $this->assertSame(['2026-09-10', '2026-09-11', '2026-09-12', '2026-09-20'], $aulas->pluck('data')->all());
        $this->assertEquals([1.5, 1, 2, 1], $aulas->pluck('horas')->all());
        $this->assertEquals([60, 50, 40, 40], $aulas->pluck('valor_hora')->all());
        $this->assertEquals([90, 50, 80, 40], $aulas->pluck('valor')->all());
        $this->assertNull($aulas[3]['turma']);
        $this->assertSame('Ana Lima', $aulas[3]['aluno']['nome']);

        $comissoes = collect($resposta->json('data.comissoes'))->keyBy('chave');
        $this->assertSame(['turma:'.$this->turmaA->id, 'turma:'.$this->turmaB->id, 'individuais'], $comissoes->keys()->all());
        $this->assertEquals(['base' => 300, 'percentual' => 20, 'valor' => 60, 'pagamentos' => 1, 'alunos' => 1],
            collect($comissoes['turma:'.$this->turmaA->id])->only(['base', 'percentual', 'valor', 'pagamentos', 'alunos'])->all());
        $this->assertEquals(['base' => 100, 'percentual' => 10, 'valor' => 10],
            collect($comissoes['turma:'.$this->turmaB->id])->only(['base', 'percentual', 'valor'])->all());
        $this->assertEquals(['base' => 450, 'percentual' => 10, 'valor' => 45, 'pagamentos' => 2, 'alunos' => 1],
            collect($comissoes['individuais'])->only(['base', 'percentual', 'valor', 'pagamentos', 'alunos'])->all());
        $this->assertNull($comissoes['individuais']['turma']);
    }

    public function test_mes_atual_e_parcial_e_mes_futuro_e_recusado(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson(self::ROTA)
            ->assertOk()
            ->assertJsonPath('data.competencia', '2026-10')
            ->assertJsonPath('data.situacao', 'em_andamento')
            ->assertJsonPath('data.resumo.total_aulas', 1)
            ->assertJsonPath('data.resumo.base_comissao', 100)
            ->assertJsonPath('data.resumo.valor_total', 1000 + 60 + 10);

        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson(self::ROTA.'?mes=2026-11')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mes']);

        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson(self::ROTA.'?mes=09-2026')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mes']);
    }

    public function test_acesso_exige_login_e_papel_correto(): void
    {
        $cabecalho = ['X-Tenant-Slug' => $this->instituicao->slug];
        $this->getJson(self::ROTA, $cabecalho)->assertUnauthorized();
        $this->getJson(self::ROTA_ADMIN.'/remuneracao', $cabecalho)->assertUnauthorized();

        $this->comoUsuario($this->secretaria, $this->instituicao)->getJson(self::ROTA)->assertForbidden();
        $this->comoUsuario($this->admin, $this->instituicao)->getJson(self::ROTA)->assertForbidden();

        foreach ([$this->usuarioProfessor, $this->secretaria] as $usuario) {
            $this->comoUsuario($usuario, $this->instituicao)->getJson(self::ROTA_ADMIN.'/remuneracao')->assertForbidden();
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson(self::ROTA_ADMIN."/{$this->professor->id}/extrato")
                ->assertForbidden();
            $this->comoUsuario($usuario, $this->instituicao)
                ->postJson(self::ROTA_ADMIN."/{$this->professor->id}/fechamentos", ['mes' => '2026-09'])
                ->assertForbidden();
        }

        $this->assertSame(0, FechamentoProfessor::withoutGlobalScopes()->count());
    }

    public function test_admin_ve_a_remuneracao_do_mes_e_o_extrato_de_cada_professor(): void
    {
        $resposta = $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson(self::ROTA_ADMIN.'/remuneracao?mes=2026-09')
            ->assertOk()
            ->assertJsonPath('competencia', '2026-09')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.professor.nome', 'Otto Prof')
            ->assertJsonPath('data.1.professor.nome', 'Paula Prof')
            ->assertJsonPath('data.1.resumo.valor_total', 1375)
            ->assertJsonPath('data.1.situacao', 'aberto');

        $this->assertEquals(60 + 300 * 0.15 + 1375, $resposta->json('total'));
        $this->assertArrayNotHasKey('aulas', $resposta->json('data.1'));

        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson(self::ROTA_ADMIN."/{$this->professor->id}/extrato?mes=2026-09")
            ->assertOk()
            ->assertJsonPath('data.resumo.valor_total', 1375)
            ->assertJsonCount(4, 'data.aulas')
            ->assertJsonCount(3, 'data.comissoes');
    }

    public function test_fechar_mes_congela_o_extrato_e_lanca_a_despesa_no_financeiro(): void
    {
        $this->fecharSetembro()
            ->assertCreated()
            ->assertJsonPath('data.situacao', 'fechado')
            ->assertJsonPath('data.resumo.valor_total', 1375)
            ->assertJsonPath('data.fechamento.conta.status', 'pendente')
            ->assertJsonPath('data.fechamento.conta.valor', 1375)
            ->assertJsonPath('data.fechamento.conta.data_vencimento', '2026-10-15');

        $conta = $this->naInstituicao($this->instituicao, fn () => Conta::with('categoria')->where('tipo', 'despesa')->sole());
        $this->assertSame($this->professor->id, (int) $conta->id_professor);
        $this->assertSame('Pagamento de professores', $conta->categoria->nome);
        $this->assertSame([9, 2026], [(int) $conta->mes_referencia, (int) $conta->ano_referencia]);
        $this->assertFalse((bool) $conta->notificar);

        $this->naInstituicao($this->instituicao, function (): void {
            $this->professor->update(['salario_fixo' => 5000]);
            $this->pagar($this->mensalidadeAna, 999, '2026-09-30');
        });

        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson(self::ROTA.'?mes=2026-09')
            ->assertOk()
            ->assertJsonPath('data.situacao', 'fechado')
            ->assertJsonPath('data.resumo.salario_fixo', 1000)
            ->assertJsonPath('data.resumo.valor_total', 1375)
            ->assertJsonPath('data.fechamento.conta.status', 'pendente');

        $this->fecharSetembro()->assertUnprocessable()->assertJsonValidationErrors(['mes']);
        $this->assertSame(1, FechamentoProfessor::withoutGlobalScopes()->count());
    }

    public function test_fechamento_aceita_vencimento_e_recusa_mes_em_andamento(): void
    {
        $this->fecharSetembro(['data_vencimento' => '2026-10-20'])
            ->assertCreated()
            ->assertJsonPath('data.fechamento.conta.data_vencimento', '2026-10-20');

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson(self::ROTA_ADMIN."/{$this->professor->id}/fechamentos", ['mes' => '2026-10'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mes']);
    }

    public function test_fechamento_sem_valor_nao_gera_despesa(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson(self::ROTA_ADMIN."/{$this->outroProfessor->id}/fechamentos", ['mes' => '2026-08'])
            ->assertCreated()
            ->assertJsonPath('data.resumo.valor_total', 0)
            ->assertJsonPath('data.fechamento.conta', null);

        $this->assertSame(0, Conta::withoutGlobalScopes()->where('tipo', 'despesa')->count());
    }

    public function test_reabrir_mes_exclui_a_despesa_pendente_e_recusa_se_ja_paga(): void
    {
        $fechamentoId = $this->fecharSetembro()->json('data.fechamento.id');
        $contaId = FechamentoProfessor::withoutGlobalScopes()->find($fechamentoId)->id_conta;
        $this->assertNotNull($contaId);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->deleteJson(self::ROTA_ADMIN."/{$this->professor->id}/fechamentos/{$fechamentoId}")
            ->assertNoContent();

        $this->assertSoftDeleted('contas', ['id' => $contaId]);
        $this->assertSoftDeleted('fechamentos_professores', ['id' => $fechamentoId]);
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson(self::ROTA.'?mes=2026-09')
            ->assertJsonPath('data.situacao', 'aberto')
            ->assertJsonPath('data.fechamento', null);

        $novoId = $this->fecharSetembro()->assertCreated()->json('data.fechamento.id');
        $novaConta = Conta::withoutGlobalScopes()->find(FechamentoProfessor::withoutGlobalScopes()->find($novoId)->id_conta);
        $this->naInstituicao($this->instituicao, fn () => ContaPagamento::create([
            'id_conta' => $novaConta->id,
            'valor_pago' => 500,
            'data_pagamento' => '2026-10-15',
            'forma_pagamento' => 'pix',
        ]));

        $this->comoUsuario($this->admin, $this->instituicao)
            ->deleteJson(self::ROTA_ADMIN."/{$this->professor->id}/fechamentos/{$novoId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fechamento']);
        $this->assertNotSoftDeleted('fechamentos_professores', ['id' => $novoId]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->deleteJson(self::ROTA_ADMIN."/{$this->outroProfessor->id}/fechamentos/{$novoId}")
            ->assertNotFound();
    }

    public function test_dados_de_outra_instituicao_ficam_isolados(): void
    {
        $outra = Instituicao::create(['slug' => 'outra-escola', 'nome_fantasia' => 'Outra Escola', 'status' => Instituicao::STATUS_ATIVO]);
        $adminOutra = $this->criarUsuarioNaInstituicao($outra, 'admin');
        $usuarioProfOutra = $this->criarUsuarioNaInstituicao($outra, 'professor', ['nome' => 'Zeca Prof']);
        $profOutra = $this->naInstituicao($outra, fn () => Professor::create(['id_usuario' => $usuarioProfOutra->id, 'salario_fixo' => 700]));

        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson(self::ROTA_ADMIN."/{$profOutra->id}/extrato?mes=2026-09")
            ->assertNotFound();
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson(self::ROTA_ADMIN."/{$profOutra->id}/fechamentos", ['mes' => '2026-09'])
            ->assertNotFound();

        $this->comoUsuario($adminOutra, $outra)
            ->getJson(self::ROTA_ADMIN.'/remuneracao?mes=2026-09')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.professor.nome', 'Zeca Prof')
            ->assertJsonPath('total', 700);

        $this->comoUsuario($usuarioProfOutra, $outra)
            ->getJson(self::ROTA.'?mes=2026-09')
            ->assertOk()
            ->assertJsonPath('data.resumo.valor_total', 700)
            ->assertJsonCount(0, 'data.aulas')
            ->assertJsonCount(0, 'data.comissoes');

        $fechamentoId = $this->fecharSetembro()->json('data.fechamento.id');
        $this->comoUsuario($adminOutra, $outra)
            ->deleteJson(self::ROTA_ADMIN."/{$profOutra->id}/fechamentos/{$fechamentoId}")
            ->assertNotFound();
    }

    public function test_snapshot_da_conclusao_cobre_aula_individual_e_aula_sem_professor(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $snapshots = app(AulaSnapshotService::class);

            $individual = $snapshots->aplicarSeConcluida(['status' => 'concluida', 'id_turma' => null, 'id_professor' => $this->professor->id]);
            $this->assertEquals([40, 10, null], [$individual['valor_hora_aula_aplicado'], $individual['percentual_comissao_aplicado'], $individual['valor_mensalidade_aplicado']]);

            $daTurma = $snapshots->aplicarSeConcluida(['status' => 'concluida', 'id_turma' => $this->turmaA->id]);
            $this->assertEquals([60, 20, 300], [$daTurma['valor_hora_aula_aplicado'], $daTurma['percentual_comissao_aplicado'], $daTurma['valor_mensalidade_aplicado']]);

            $agendada = $snapshots->aplicarSeConcluida(['status' => 'agendada', 'id_turma' => $this->turmaA->id]);
            $this->assertArrayNotHasKey('valor_hora_aula_aplicado', $agendada);
        });
    }
}
