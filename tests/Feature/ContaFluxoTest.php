<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaPagamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class ContaFluxoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $admin;

    private CategoriaConta $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $this->categoria = $this->naInstituicao(
            $this->instituicao,
            fn () => CategoriaConta::create(['nome' => 'Mensalidades', 'tipo' => 'receita'])
        );
    }

    private function criarConta(array $atributos = []): Conta
    {
        return $this->naInstituicao($this->instituicao, fn () => Conta::create($atributos + [
            'id_categoria' => $this->categoria->id,
            'descricao' => 'Conta',
            'valor' => 100,
            'data_vencimento' => now()->toDateString(),
            'status' => 'pendente',
            'tipo' => 'receita',
        ]));
    }

    private function contasOrdenadas(): \Illuminate\Support\Collection
    {
        return Conta::withoutInstituicaoScope()->orderBy('numero_parcela')->get();
    }

    public function test_cria_conta_simples(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/contas', [
                'id_categoria' => $this->categoria->id,
                'descricao' => 'Aluguel',
                'valor' => 1200,
                'data_vencimento' => '2026-05-10',
                'tipo' => 'despesa',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('contas', [
            'descricao' => 'Aluguel',
            'id_instituicao' => $this->instituicao->id,
        ]);
    }

    public function test_repeticao_mensal_gera_parcelas_com_referencia(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/contas', [
                'id_categoria' => $this->categoria->id,
                'descricao' => 'Mensalidade',
                'valor' => 150,
                'data_vencimento' => '2026-01-10',
                'tipo' => 'receita',
                'repeticao' => 'mensal',
                'quantidade_repeticoes' => 3,
            ])
            ->assertSuccessful()
            ->assertJsonCount(3, 'data');

        $contas = $this->contasOrdenadas();

        $this->assertSame(['2026-01-10', '2026-02-10', '2026-03-10'], $contas->map(fn ($c) => $c->data_vencimento->toDateString())->all());
        $this->assertSame([1, 2, 3], $contas->pluck('numero_parcela')->map(fn ($n) => (int) $n)->all());
        $this->assertSame([3, 3, 3], $contas->pluck('quantidade_parcelas')->map(fn ($n) => (int) $n)->all());
        $this->assertSame([1, 2, 3], $contas->pluck('mes_referencia')->map(fn ($n) => (int) $n)->all());
        $this->assertSame([2026, 2026, 2026], $contas->pluck('ano_referencia')->map(fn ($n) => (int) $n)->all());
    }

    public function test_repeticao_mensal_no_fim_do_mes_nao_pula_mes(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/contas', [
                'id_categoria' => $this->categoria->id,
                'descricao' => 'Mensalidade',
                'valor' => 150,
                'data_vencimento' => '2026-01-31',
                'tipo' => 'receita',
                'repeticao' => 'mensal',
                'quantidade_repeticoes' => 3,
            ])
            ->assertSuccessful();

        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31'],
            $this->contasOrdenadas()->map(fn ($c) => $c->data_vencimento->toDateString())->all()
        );
    }

    public function test_repeticao_semanal_e_diaria(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/contas', [
                'id_categoria' => $this->categoria->id,
                'descricao' => 'Semanal',
                'valor' => 50,
                'data_vencimento' => '2026-03-02',
                'tipo' => 'receita',
                'repeticao' => 'semanal',
                'quantidade_repeticoes' => 3,
            ])
            ->assertSuccessful();

        $semanais = Conta::withoutInstituicaoScope()->where('descricao', 'Semanal')->orderBy('numero_parcela')->get();
        $this->assertSame(['2026-03-02', '2026-03-09', '2026-03-16'], $semanais->map(fn ($c) => $c->data_vencimento->toDateString())->all());
        $this->assertSame([null, null, null], $semanais->pluck('mes_referencia')->all());

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/contas', [
                'id_categoria' => $this->categoria->id,
                'descricao' => 'Diaria',
                'valor' => 50,
                'data_vencimento' => '2026-03-30',
                'tipo' => 'receita',
                'repeticao' => 'diaria',
                'quantidade_repeticoes' => 3,
            ])
            ->assertSuccessful();

        $diarias = Conta::withoutInstituicaoScope()->where('descricao', 'Diaria')->orderBy('numero_parcela')->get();
        $this->assertSame(['2026-03-30', '2026-03-31', '2026-04-01'], $diarias->map(fn ($c) => $c->data_vencimento->toDateString())->all());
    }

    public function test_filtros_por_situacao(): void
    {
        $aVencer = $this->criarConta(['descricao' => 'a vencer', 'data_vencimento' => now()->addDays(3)->toDateString()]);
        $hoje = $this->criarConta(['descricao' => 'hoje', 'data_vencimento' => now()->toDateString()]);
        $atrasada = $this->criarConta(['descricao' => 'atrasada', 'data_vencimento' => now()->subDays(3)->toDateString()]);
        $vencida = $this->criarConta(['descricao' => 'vencida', 'status' => 'vencido', 'data_vencimento' => now()->subDays(10)->toDateString()]);
        $paga = $this->criarConta(['descricao' => 'paga', 'status' => 'pago']);

        $esperado = [
            'a_vencer' => [$aVencer->id],
            'vencendo_hoje' => [$hoje->id],
            'atrasadas' => [$atrasada->id, $vencida->id],
            'pagas' => [$paga->id],
        ];

        foreach ($esperado as $situacao => $ids) {
            $response = $this->comoUsuario($this->admin, $this->instituicao)
                ->getJson("/api/contas?situacao={$situacao}")
                ->assertOk();

            $this->assertEqualsCanonicalizing($ids, collect($response->json('data'))->pluck('id')->all(), $situacao);
        }
    }

    public function test_sort_by_invalido_nao_quebra_listagem(): void
    {
        $this->criarConta();

        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson('/api/contas?sort_by=coluna_inexistente&sort_order=invalida')
            ->assertOk();
    }

    public function test_pagamento_parcial_total_e_estorno(): void
    {
        $conta = $this->criarConta(['valor' => 100]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/contas/{$conta->id}/pagamentos", [
                'valor_pago' => 40,
                'data_pagamento' => now()->toDateString(),
                'forma_pagamento' => 'pix',
            ])
            ->assertCreated();
        $this->assertSame('pago_parcialmente', $conta->fresh()->status);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/contas/{$conta->id}/pagamentos", [
                'valor_pago' => 70,
                'data_pagamento' => now()->toDateString(),
                'forma_pagamento' => 'pix',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('valor_pago');

        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/contas/{$conta->id}/pagamentos", [
                'valor_pago' => 60,
                'data_pagamento' => now()->toDateString(),
                'forma_pagamento' => 'pix',
            ])
            ->assertCreated();
        $this->assertSame('pago', $conta->fresh()->status);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->deleteJson('/api/pagamentos/'.$response->json('data.id'))
            ->assertNoContent();
        $this->assertSame('pago_parcialmente', $conta->fresh()->status);

        $idPrimeiro = ContaPagamento::withoutGlobalScopes()->where('id_conta', $conta->id)->value('id');
        $this->comoUsuario($this->admin, $this->instituicao)
            ->deleteJson("/api/pagamentos/{$idPrimeiro}")
            ->assertNoContent();
        $this->assertSame('pendente', $conta->fresh()->status);
    }

    public function test_pagamento_em_lote_quita_saldo_e_ignora_pagas(): void
    {
        $parcial = $this->criarConta(['valor' => 100, 'status' => 'pago_parcialmente']);
        $this->naInstituicao($this->instituicao, fn () => $parcial->pagamentos()->create([
            'valor_pago' => 30,
            'data_pagamento' => now()->toDateString(),
            'forma_pagamento' => 'pix',
        ]));
        $pendente = $this->criarConta(['valor' => 50]);
        $jaPaga = $this->criarConta(['valor' => 80, 'status' => 'pago']);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/contas/pagamentos/lote', [
                'conta_ids' => [$parcial->id, $pendente->id, $jaPaga->id],
                'data_pagamento' => now()->toDateString(),
                'forma_pagamento' => 'dinheiro',
            ])
            ->assertOk()
            ->assertJsonPath('contas_afetadas', [$parcial->id, $pendente->id]);

        $this->assertSame('pago', $parcial->fresh()->status);
        $this->assertSame('pago', $pendente->fresh()->status);
        $this->assertEquals(70, $parcial->pagamentos()->latest('id')->value('valor_pago'));
        $this->assertSame(0, $jaPaga->pagamentos()->count());
    }

    public function test_aluno_ve_apenas_as_proprias_contas(): void
    {
        $usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $outroUsuario = User::factory()->create(['role' => 'aluno']);

        [$aluno, $outroAluno] = $this->naInstituicao($this->instituicao, fn () => [
            Aluno::create(['id_usuario' => $usuarioAluno->id]),
            Aluno::create(['id_usuario' => $outroUsuario->id]),
        ]);

        $minha = $this->criarConta(['id_aluno' => $aluno->id]);
        $alheia = $this->criarConta(['id_aluno' => $outroAluno->id]);

        $ids = collect(
            $this->comoUsuario($usuarioAluno, $this->instituicao)->getJson('/api/contas')->assertOk()->json('data')
        )->pluck('id')->all();
        $this->assertSame([$minha->id], $ids);

        $this->comoUsuario($usuarioAluno, $this->instituicao)
            ->getJson("/api/contas/{$minha->id}")
            ->assertOk();

        $this->comoUsuario($usuarioAluno, $this->instituicao)
            ->getJson("/api/contas/{$alheia->id}")
            ->assertForbidden();

        $this->comoUsuario($usuarioAluno, $this->instituicao)
            ->getJson("/api/contas/{$alheia->id}/pagamentos")
            ->assertForbidden();
    }
}
