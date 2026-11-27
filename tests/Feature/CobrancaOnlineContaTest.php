<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\Support\PlatformSettings;
use App\Modules\Financeiro\Jobs\SyncCobrancasContaGatewayJob;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaCobrancaGateway;
use App\Modules\Financeiro\Models\ContaPagamento;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class CobrancaOnlineContaTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private const GATEWAY = 'http://gateway.test';

    private const API_KEY = 'cpk_testkey1234567890';

    private const PAY_UUID = 'pay-uuid-0001';

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioBruno;

    private User $usuarioRita;

    private User $secretaria;

    private Aluno $ana;

    private Aluno $bruno;

    private Conta $contaAna;

    private Conta $contaBruno;

    /** Status devolvido pelo gateway falso em GET /payments/{uuid}. */
    private string $statusRemoto = 'awaiting_payment';

    private bool $comInvoice = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-26 09:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $this->usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo', 'cpf' => '529.982.247-25']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima', 'cpf' => '111.444.777-35']);
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');

        $this->naInstituicao($this->instituicao, function (): void {
            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $this->usuarioBruno->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $categoria = CategoriaConta::firstOrCreate(['nome' => 'Mensalidades'], ['tipo' => 'receita']);

            $this->contaAna = $this->criarConta($categoria->id, $this->ana->id, 'Mensalidade da Ana', 100);
            $this->contaBruno = $this->criarConta($categoria->id, $this->bruno->id, 'Mensalidade do Bruno', 90);
        });

        $this->habilitarBilling();
        $this->ativarSubconta();
        $this->fakeGateway();
    }

    private function criarConta(int $categoriaId, int $alunoId, string $descricao, float $valor): Conta
    {
        return Conta::create([
            'id_categoria' => $categoriaId,
            'id_aluno' => $alunoId,
            'descricao' => $descricao,
            'valor' => $valor,
            'data_vencimento' => '2026-11-10',
            'status' => 'pendente',
            'tipo' => 'receita',
        ]);
    }

    private function habilitarBilling(bool $enabled = true): void
    {
        app(PlatformSettings::class)->setMany([
            PlatformSettings::BILLING_GATEWAY_URL => self::GATEWAY,
            PlatformSettings::BILLING_GATEWAY_API_KEY => self::API_KEY,
            PlatformSettings::BILLING_ENABLED => $enabled ? '1' : '0',
        ]);
    }

    private function ativarSubconta(): void
    {
        $this->instituicao->update([
            'gateway_subaccount_uuid' => 'acc-uuid-0001',
            'gateway_subaccount_status' => Instituicao::SUBCONTA_ACTIVE,
        ]);
    }

    private function fakeGateway(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            $base = self::GATEWAY.'/api/v1/payments';

            if ($request->method() === 'POST' && $url === $base) {
                return Http::response(['success' => true, 'data' => ['id' => self::PAY_UUID, 'status' => 'pending']], 202);
            }

            if ($request->method() === 'POST' && $url === $base.'/'.self::PAY_UUID.'/cancel') {
                return Http::response(['success' => true, 'data' => ['id' => self::PAY_UUID, 'status' => 'canceled']], 202);
            }

            if ($request->method() === 'GET' && str_starts_with($url, $base.'/'.self::PAY_UUID)) {
                return Http::response(['data' => [
                    'id' => self::PAY_UUID,
                    'status' => $this->statusRemoto,
                    'invoice_url' => $this->comInvoice ? 'https://pay.test/i/abc' : null,
                    'pix_copy_paste' => $this->comInvoice ? '000201PIXCOPIAECOLA' : null,
                    'pix_qr_code' => $this->comInvoice ? 'BASE64QR' : null,
                ]]);
            }

            return Http::response(['message' => 'not faked'], 404);
        });
    }

    private function criarCobrancaLocal(Conta $conta, string $status = ContaCobrancaGateway::STATUS_AWAITING_PAYMENT, float $amount = 100): ContaCobrancaGateway
    {
        return ContaCobrancaGateway::query()->create([
            'id_conta' => $conta->id,
            'id_instituicao' => $this->instituicao->id,
            'gateway_payment_uuid' => self::PAY_UUID,
            'method' => 'pix',
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    private function enviosAoGateway(string $method, string $sufixo = ''): int
    {
        return Http::recorded(fn (Request $request) => $request->method() === $method
            && str_starts_with($request->url(), self::GATEWAY.'/api/v1/payments'.$sufixo))->count();
    }

    public function test_criar_cobranca_exige_subconta_ativa_e_meio_valido(): void
    {
        $rota = '/api/aluno/me/contas/'.$this->contaAna->id.'/cobranca-online';
        $cliente = $this->comoUsuario($this->usuarioRita, $this->instituicao);

        // método fora da lista aceita pela API
        $cliente->postJson($rota, ['method' => 'dinheiro', 'id_aluno' => $this->ana->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('method');

        // método válido na API, mas desligado para a escola (padrão: pix e boleto)
        $cliente->postJson($rota, ['method' => 'credit_card', 'id_aluno' => $this->ana->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('method');

        $this->instituicao->update(['gateway_subaccount_status' => Instituicao::SUBCONTA_PENDING]);

        $cliente->postJson($rota, ['method' => 'pix', 'id_aluno' => $this->ana->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('billing');

        $this->assertDatabaseCount('contas_cobrancas_gateway', 0);
        $this->assertSame(0, $this->enviosAoGateway('POST'));
    }

    public function test_responsavel_cria_cobranca_pix_na_subconta_e_reaproveita_a_aberta(): void
    {
        $rota = '/api/aluno/me/contas/'.$this->contaAna->id.'/cobranca-online';
        $cliente = $this->comoUsuario($this->usuarioRita, $this->instituicao);

        $primeira = $cliente->postJson($rota, ['method' => 'pix', 'id_aluno' => $this->ana->id])
            ->assertCreated()
            ->assertJsonPath('data.status', ContaCobrancaGateway::STATUS_AWAITING_PAYMENT)
            ->assertJsonPath('data.method', 'pix')
            ->assertJsonPath('data.aberta', true)
            ->assertJsonPath('data.invoice_url', 'https://pay.test/i/abc')
            ->assertJsonPath('data.pix_copy_paste', '000201PIXCOPIAECOLA');

        $this->assertEquals(100, $primeira->json('data.amount'));

        $this->assertDatabaseHas('contas_cobrancas_gateway', [
            'id_conta' => $this->contaAna->id,
            'id_instituicao' => $this->instituicao->id,
            'gateway_payment_uuid' => self::PAY_UUID,
            'status' => ContaCobrancaGateway::STATUS_AWAITING_PAYMENT,
        ]);

        $instituicaoId = $this->instituicao->id;
        $alunoId = $this->ana->id;

        Http::assertSent(function (Request $request) use ($instituicaoId, $alunoId): bool {
            if ($request->method() !== 'POST' || $request->url() !== self::GATEWAY.'/api/v1/payments') {
                return false;
            }

            return $request->hasHeader('X-API-Key', self::API_KEY)
                && ! array_key_exists('retain_on_platform', $request->data())
                && $request['subscriber_id'] === 'instituicao:'.$instituicaoId
                && $request['customer_id'] === 'aluno:'.$alunoId
                && (float) $request['amount'] === 100.0
                && $request['method'] === 'pix'
                && $request['due_date'] === '2026-11-10'
                && $request['customer']['name'] === 'Rita Lima'
                && $request['customer']['cpf_cnpj'] === '11144477735'
                && $request['customer']['email'] === $this->usuarioRita->email;
        });

        // segunda chamada reaproveita a cobrança aberta (mesmo com outro meio)
        $cliente->postJson($rota, ['method' => 'boleto', 'id_aluno' => $this->ana->id])
            ->assertOk()
            ->assertJsonPath('data.id', $primeira->json('data.id'))
            ->assertJsonPath('data.method', 'pix');

        $this->assertDatabaseCount('contas_cobrancas_gateway', 1);
        $this->assertSame(1, $this->enviosAoGateway('POST'));
    }

    public function test_sem_responsavel_o_pagador_e_o_proprio_aluno(): void
    {
        $this->comoUsuario($this->usuarioBruno, $this->instituicao)
            ->postJson('/api/aluno/me/contas/'.$this->contaBruno->id.'/cobranca-online', ['method' => 'boleto'])
            ->assertCreated();

        $brunoId = $this->bruno->id;

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === self::GATEWAY.'/api/v1/payments'
            && $request['customer_id'] === 'aluno:'.$brunoId
            && $request['customer']['name'] === 'Bruno Melo'
            && $request['customer']['cpf_cnpj'] === '52998224725');
    }

    public function test_billing_desligado_retorna_422(): void
    {
        $this->habilitarBilling(false);

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->postJson('/api/aluno/me/contas/'.$this->contaAna->id.'/cobranca-online', ['method' => 'pix'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('billing');

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/contas/'.$this->contaAna->id.'/cobranca-online', ['method' => 'pix'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('billing');

        $this->assertDatabaseCount('contas_cobrancas_gateway', 0);
    }

    public function test_conta_quitada_nao_gera_cobranca(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            ContaPagamento::create([
                'id_conta' => $this->contaAna->id,
                'valor_pago' => 100,
                'data_pagamento' => '2026-10-20',
                'forma_pagamento' => 'dinheiro',
            ]);
            $this->contaAna->recalcularStatus();
        });

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->postJson('/api/aluno/me/contas/'.$this->contaAna->id.'/cobranca-online', ['method' => 'pix'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('conta');
    }

    public function test_sync_pago_registra_pagamento_e_quita_a_conta_de_forma_idempotente(): void
    {
        $cobranca = $this->criarCobrancaLocal($this->contaAna);
        $this->statusRemoto = 'paid';
        $cliente = $this->comoUsuario($this->usuarioAna, $this->instituicao);
        $rota = '/api/aluno/me/contas/'.$this->contaAna->id.'/cobranca-online?sync=1';

        $cliente->getJson($rota)
            ->assertOk()
            ->assertJsonPath('data.status', ContaCobrancaGateway::STATUS_PAID)
            ->assertJsonPath('data.paga', true)
            ->assertJsonPath('data.aberta', false);

        $cobranca->refresh();
        $this->assertNotNull($cobranca->id_conta_pagamento);

        $pagamento = ContaPagamento::query()->findOrFail($cobranca->id_conta_pagamento);
        $this->assertEquals(100, $pagamento->valor_pago);
        $this->assertSame('pix', $pagamento->forma_pagamento);
        $this->assertSame('2026-10-26', substr((string) $pagamento->data_pagamento, 0, 10));
        $this->assertStringContainsString(self::PAY_UUID, (string) $pagamento->observacoes);
        $this->assertSame($this->instituicao->id, $pagamento->id_instituicao);
        $this->assertSame('pago', $this->contaAna->fresh()->status);

        // novas consultas não duplicam o pagamento
        $cliente->getJson($rota)->assertOk()->assertJsonPath('data.status', ContaCobrancaGateway::STATUS_PAID);

        $this->assertSame(1, ContaPagamento::query()->where('id_conta', $this->contaAna->id)->count());
        $this->assertSame(1, $this->enviosAoGateway('GET'));
    }

    public function test_job_sincroniza_cobrancas_abertas_sem_contexto_de_instituicao(): void
    {
        $cobranca = $this->criarCobrancaLocal($this->contaAna);
        $this->statusRemoto = 'paid';

        InstituicaoContext::clear();
        (new SyncCobrancasContaGatewayJob)->handle(
            app(\App\Modules\Core\Services\BillingGatewayClient::class),
            app(\App\Modules\Financeiro\UseCases\CobrancaOnline\SyncCobrancaOnlineContaUseCase::class),
        );

        $cobranca->refresh();
        $this->assertSame(ContaCobrancaGateway::STATUS_PAID, $cobranca->status);
        $this->assertNotNull($cobranca->id_conta_pagamento);
        $this->assertSame($this->instituicao->id, ContaPagamento::query()->findOrFail($cobranca->id_conta_pagamento)->id_instituicao);
        $this->assertSame('pago', Conta::query()->findOrFail($this->contaAna->id)->status);

        // segunda execução ignora cobranças finais
        (new SyncCobrancasContaGatewayJob)->handle(
            app(\App\Modules\Core\Services\BillingGatewayClient::class),
            app(\App\Modules\Financeiro\UseCases\CobrancaOnline\SyncCobrancaOnlineContaUseCase::class),
        );
        $this->assertSame(1, $this->enviosAoGateway('GET'));
        $this->assertSame(1, ContaPagamento::query()->where('id_conta', $this->contaAna->id)->count());
    }

    public function test_aluno_so_acessa_a_propria_conta(): void
    {
        $this->criarCobrancaLocal($this->contaBruno);
        $cliente = $this->comoUsuario($this->usuarioAna, $this->instituicao);
        $rota = '/api/aluno/me/contas/'.$this->contaBruno->id.'/cobranca-online';

        $cliente->postJson($rota, ['method' => 'pix'])->assertForbidden();
        $cliente->getJson($rota.'?sync=1')->assertForbidden();

        $this->assertSame(0, $this->enviosAoGateway('POST'));
        $this->assertSame(0, $this->enviosAoGateway('GET'));

        // e a mesma cobrança continua visível para o dono
        $this->comoUsuario($this->usuarioBruno, $this->instituicao)
            ->getJson($rota)
            ->assertOk()
            ->assertJsonPath('data.id_conta', $this->contaBruno->id);
    }

    public function test_get_sem_cobranca_retorna_data_nula(): void
    {
        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/contas/'.$this->contaAna->id.'/cobranca-online')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_secretaria_gera_e_cancela_cobranca_e_aluno_nao_usa_rotas_da_escola(): void
    {
        $rota = '/api/contas/'.$this->contaAna->id.'/cobranca-online';

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->postJson($rota, ['method' => 'pix'])
            ->assertForbidden();

        $secretaria = $this->comoUsuario($this->secretaria, $this->instituicao);

        $secretaria->postJson($rota, ['method' => 'boleto'])
            ->assertCreated()
            ->assertJsonPath('data.method', 'boleto');

        $secretaria->getJson($rota)
            ->assertOk()
            ->assertJsonPath('data.status', ContaCobrancaGateway::STATUS_AWAITING_PAYMENT);

        $secretaria->postJson($rota.'/cancelar')
            ->assertOk()
            ->assertJsonPath('data.status', ContaCobrancaGateway::STATUS_CANCELED)
            ->assertJsonPath('data.aberta', false);

        $this->assertSame(1, $this->enviosAoGateway('POST', '/'.self::PAY_UUID.'/cancel'));

        // sem cobrança aberta não há o que cancelar
        $secretaria->postJson($rota.'/cancelar')->assertStatus(422)->assertJsonValidationErrors('cobranca');
    }

    public function test_nao_cancela_cobranca_ja_paga_no_gateway(): void
    {
        $cobranca = $this->criarCobrancaLocal($this->contaAna);
        $this->statusRemoto = 'paid';

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/contas/'.$this->contaAna->id.'/cobranca-online/cancelar')
            ->assertStatus(422)
            ->assertJsonValidationErrors('cobranca');

        $this->assertSame(0, $this->enviosAoGateway('POST', '/'.self::PAY_UUID.'/cancel'));
        $this->assertSame(ContaCobrancaGateway::STATUS_PAID, $cobranca->fresh()->status);
        $this->assertSame('pago', $this->contaAna->fresh()->status);
    }

    public function test_lista_do_aluno_expoe_disponibilidade_e_cobranca_aberta(): void
    {
        $cliente = $this->comoUsuario($this->usuarioAna, $this->instituicao);

        $antes = $cliente->getJson('/api/aluno/me/contas')->assertOk()->json('data.0.cobranca_online');
        $this->assertTrue($antes['disponivel']);
        $this->assertSame(['pix', 'boleto'], $antes['meios']);
        $this->assertNull($antes['cobranca_aberta']);

        $this->criarCobrancaLocal($this->contaAna);

        $depois = $cliente->getJson('/api/aluno/me/contas')->assertOk()->json('data.0.cobranca_online');
        $this->assertSame(ContaCobrancaGateway::STATUS_AWAITING_PAYMENT, $depois['cobranca_aberta']['status']);

        $this->instituicao->update(['gateway_subaccount_status' => Instituicao::SUBCONTA_NONE]);
        // O serviço de disponibilidade é scoped (memoiza por requisição); no teste a app é reaproveitada.
        $this->app->forgetScopedInstances();

        $semSubconta = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/contas')->assertOk()->json('data.0.cobranca_online');
        $this->assertFalse($semSubconta['disponivel']);
        $this->assertSame([], $semSubconta['meios']);
    }
}
