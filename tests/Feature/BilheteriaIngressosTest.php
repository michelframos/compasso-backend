<?php

namespace Tests\Feature;

use App\Modules\Bilheteria\Models\CupomIngresso;
use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Espetaculos\Models\Espetaculo;
use App\Modules\Financeiro\Models\Conta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class BilheteriaIngressosTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-04 18:00:00');
        $this->instituicao = $this->instituicaoDefault();
    }

    public function test_secretaria_gera_vende_paga_e_utiliza_ingresso(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');

        $espetaculo = $this->naInstituicao($this->instituicao, function () {
            return Espetaculo::create([
                'titulo' => 'Noite de Gala',
                'data_evento' => '2026-11-04',
                'hora_evento' => '20:00',
                'local' => 'Teatro',
                'valor_ingresso_padrao' => 50,
            ]);
        });

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson("/api/espetaculos/{$espetaculo->id}/ingressos/gerar", [
                'quantidade' => 2,
                'valor' => 50,
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $ingresso = Ingresso::query()->first();
        $this->assertSame(Ingresso::STATUS_DISPONIVEL, $ingresso->status);
        $this->assertNotEmpty($ingresso->codigo_qr);

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson("/api/ingressos/{$ingresso->id}/vender", [
                'comprador_nome' => 'Maria',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'vendido')
            ->assertJsonPath('data.status_pagamento', 'aguardando_pagamento');

        $ingresso->refresh();
        $this->assertNotNull($ingresso->id_conta);
        $conta = Conta::query()->findOrFail($ingresso->id_conta);
        $this->assertSame('pendente', $conta->status);
        $this->assertEquals(50, (float) $conta->valor);

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson("/api/contas/{$conta->id}/pagamentos", [
                'valor_pago' => 50,
                'data_pagamento' => '2026-11-04',
                'forma_pagamento' => 'dinheiro',
            ])
            ->assertCreated();

        $ingresso->refresh();
        $this->assertSame(Ingresso::PAGAMENTO_PAGO, $ingresso->status_pagamento);

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/ingressos/utilizar', [
                'codigo_qr' => $ingresso->codigo_qr,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $ingresso->refresh();
        $this->assertSame(Ingresso::STATUS_UTILIZADO, $ingresso->status);

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/ingressos/utilizar', [
                'codigo_qr' => $ingresso->codigo_qr,
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('motivo', 'O ingresso já foi utilizado.');
    }

    public function test_cortesia_marca_vendido_pago_sem_conta(): void
    {
        $admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $espetaculo = $this->naInstituicao($this->instituicao, fn () => Espetaculo::create([
            'titulo' => 'Show',
            'data_evento' => '2026-11-04',
            'valor_ingresso_padrao' => 30,
        ]));

        $this->comoUsuario($admin, $this->instituicao)
            ->postJson("/api/espetaculos/{$espetaculo->id}/ingressos/gerar", ['quantidade' => 1])
            ->assertCreated();

        $ingresso = Ingresso::query()->first();

        $this->comoUsuario($admin, $this->instituicao)
            ->postJson("/api/ingressos/{$ingresso->id}/cortesia")
            ->assertOk()
            ->assertJsonPath('data.status', 'vendido')
            ->assertJsonPath('data.cortesia', true)
            ->assertJsonPath('data.status_pagamento', 'pago')
            ->assertJsonPath('data.status_rotulo', 'vendido (cortesia)');

        $this->assertNull($ingresso->fresh()->id_conta);
        $this->assertSame(0.0, (float) $ingresso->fresh()->valor);
    }

    public function test_validacao_rejeita_fora_do_periodo(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $espetaculo = $this->naInstituicao($this->instituicao, fn () => Espetaculo::create([
            'titulo' => 'Futuro',
            'data_evento' => '2026-12-01',
        ]));

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson("/api/espetaculos/{$espetaculo->id}/ingressos/gerar", ['quantidade' => 1])
            ->assertCreated();

        $ingresso = Ingresso::query()->first();
        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson("/api/ingressos/{$ingresso->id}/cortesia")
            ->assertOk();

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/ingressos/validar', ['numero' => $ingresso->numero])
            ->assertStatus(422)
            ->assertJsonPath('motivo', 'O ingresso é inválido (fora do período de uso).');
    }

    public function test_cupom_percentual_aplica_desconto_na_venda(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $espetaculo = $this->naInstituicao($this->instituicao, fn () => Espetaculo::create([
            'titulo' => 'Com cupom',
            'data_evento' => '2026-11-04',
            'valor_ingresso_padrao' => 100,
        ]));

        $this->naInstituicao($this->instituicao, function () use ($espetaculo) {
            CupomIngresso::create([
                'codigo' => 'PROMO10',
                'tipo' => 'percentual',
                'valor' => 10,
                'ativo' => true,
                'id_espetaculo' => $espetaculo->id,
            ]);
        });

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson("/api/espetaculos/{$espetaculo->id}/ingressos/gerar", ['quantidade' => 1])
            ->assertCreated();

        $ingresso = Ingresso::query()->first();

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson("/api/ingressos/{$ingresso->id}/vender", ['cupom' => 'PROMO10'])
            ->assertOk()
            ->assertJsonPath('data.valor', 90);

        $this->assertEquals(90, (float) Conta::query()->first()->valor);
    }

    public function test_template_com_dimensoes_e_qr_no_snapshot(): void
    {
        $admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');

        $templateRes = $this->comoUsuario($admin, $this->instituicao)
            ->postJson('/api/templates-ingressos', [
                'nome' => 'Ticket 15x7',
                'largura' => 15,
                'altura' => 7,
                'unidade' => 'cm',
                'conteudo' => json_encode([
                    'version' => 1,
                    'elementos' => [
                        [
                            'id' => 'el1',
                            'tipo' => 'texto',
                            'x' => 5,
                            'y' => 5,
                            'w' => 50,
                            'h' => 20,
                            'texto' => 'N {{numero}}',
                            'fonteTamanho' => 14,
                            'fontePeso' => 'bold',
                            'alinhamento' => 'left',
                            'cor' => '#000',
                        ],
                        [
                            'id' => 'el2',
                            'tipo' => 'qrcode',
                            'x' => 60,
                            'y' => 20,
                            'w' => 30,
                            'h' => 50,
                        ],
                    ],
                ]),
            ])
            ->assertCreated();

        $templateId = $templateRes->json('data.id');
        $this->assertSame(15.0, (float) $templateRes->json('data.largura'));
        $this->assertSame('cm', $templateRes->json('data.unidade'));

        $espetaculo = $this->naInstituicao($this->instituicao, fn () => Espetaculo::create([
            'titulo' => 'Com template',
            'data_evento' => '2026-11-04',
            'id_template_ingresso' => $templateId,
            'valor_ingresso_padrao' => 20,
        ]));

        $this->comoUsuario($admin, $this->instituicao)
            ->postJson("/api/espetaculos/{$espetaculo->id}/ingressos/gerar", [
                'quantidade' => 1,
                'id_template_ingresso' => $templateId,
            ])
            ->assertCreated();

        $ingresso = Ingresso::query()->first();
        $this->assertNotNull($ingresso->conteudo_gerado);
        $this->assertStringContainsString('N 1', $ingresso->conteudo_gerado);
        $this->assertStringContainsString('data:image/png;base64,', $ingresso->conteudo_gerado);
    }

    public function test_professor_nao_acessa_bilheteria(): void
    {
        $professor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');

        $this->comoUsuario($professor, $this->instituicao)
            ->getJson('/api/ingressos')
            ->assertForbidden();
    }

    public function test_modulo_ausente_bloqueia_api(): void
    {
        $plano = PlanoAssinatura::query()->create([
            'slug' => 'sem-bilheteria-'.uniqid(),
            'nome' => 'Sem Bilheteria',
            'preco_mensal' => 10,
            'limite_alunos' => 10,
            'modulos' => ['leads'],
            'ativo' => true,
        ]);

        $inst = Instituicao::create([
            'slug' => 'escola-sem-bilh-'.uniqid(),
            'nome_fantasia' => 'Sem Bilheteria',
            'status' => Instituicao::STATUS_ATIVO,
            'id_plano_assinatura' => $plano->id,
            'trial_ends_at' => null,
            'trial_usa_padrao' => false,
            'assinatura_status' => 'active',
        ]);

        $admin = $this->criarUsuarioNaInstituicao($inst, 'admin');

        $this->comoUsuario($admin, $inst)
            ->getJson('/api/ingressos')
            ->assertForbidden()
            ->assertJsonPath('code', 'modulo_nao_incluido');
    }

    public function test_relatorio_agrega_por_espetaculo(): void
    {
        $admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $espetaculo = $this->naInstituicao($this->instituicao, fn () => Espetaculo::create([
            'titulo' => 'Relatório',
            'data_evento' => '2026-11-04',
            'valor_ingresso_padrao' => 40,
        ]));

        $this->comoUsuario($admin, $this->instituicao)
            ->postJson("/api/espetaculos/{$espetaculo->id}/ingressos/gerar", ['quantidade' => 3])
            ->assertCreated();

        $ids = Ingresso::query()->pluck('id');
        $this->comoUsuario($admin, $this->instituicao)
            ->postJson("/api/ingressos/{$ids[0]}/cortesia")
            ->assertOk();

        $this->comoUsuario($admin, $this->instituicao)
            ->getJson('/api/ingressos/relatorio?id_espetaculo='.$espetaculo->id)
            ->assertOk()
            ->assertJsonPath('data.gerados', 3)
            ->assertJsonPath('data.disponiveis', 2)
            ->assertJsonPath('data.cortesias', 1);
    }
}
