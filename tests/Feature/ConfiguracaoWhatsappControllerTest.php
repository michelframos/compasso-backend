<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Notificacoes\Exceptions\WhatsappGatewayException;
use App\Modules\Notificacoes\Jobs\EnviarMensagemWhatsappJob;
use App\Modules\Notificacoes\Models\ConfiguracaoNotificacao;
use App\Modules\Notificacoes\Models\ConfiguracaoWhatsapp;
use App\Modules\Notificacoes\Models\NotificacaoDisparada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class ConfiguracaoWhatsappControllerTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private const API = 'http://whatsapp.test';

    private Instituicao $instituicao;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.url' => self::API]);

        $this->instituicao = $this->instituicaoDefault();
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
    }

    private function criarConfig(string $status = 'pending'): ConfiguracaoWhatsapp
    {
        return $this->naInstituicao($this->instituicao, fn () => ConfiguracaoWhatsapp::create([
            'instance_name' => 'escola',
            'status' => $status,
        ]));
    }

    public function test_show_sem_configuracao_retorna_desconectado(): void
    {
        Http::fake();

        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson('/api/whatsapp/config')
            ->assertOk()
            ->assertJsonPath('instance_name', null)
            ->assertJsonPath('status', 'disconnected');

        Http::assertNothingSent();
    }

    public function test_show_sincroniza_status_com_a_api(): void
    {
        $config = $this->criarConfig('pending');

        Http::fake([
            self::API.'/api/instance/status/escola' => Http::response(['instance' => ['state' => 'open']]),
        ]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson('/api/whatsapp/config')
            ->assertOk()
            ->assertJsonPath('instance_name', 'escola')
            ->assertJsonPath('status', 'connected');

        $this->assertSame('connected', $config->fresh()->status);
    }

    public function test_show_com_api_offline_retorna_status_salvo(): void
    {
        $this->criarConfig('connected');

        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson('/api/whatsapp/config')
            ->assertOk()
            ->assertJsonPath('status', 'connected');
    }

    public function test_connect_retorna_qr_code_e_salva_configuracao(): void
    {
        Http::fake([
            self::API.'/api/instance/connect' => Http::response(['qrcode' => ['base64' => 'QR123']]),
        ]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/whatsapp/connect', ['instance_name' => 'escola'])
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('qr_code', 'QR123');

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/api/instance/connect'
            && $r['instance_name'] === 'escola');

        $this->assertDatabaseHas('configuracoes_whatsapp', [
            'instance_name' => 'escola',
            'status' => 'pending',
            'id_instituicao' => $this->instituicao->id,
        ]);
    }

    public function test_connect_com_instancia_existente_faz_reconnect(): void
    {
        Http::fake([
            self::API.'/api/instance/connect' => Http::response(['message' => 'This name is already in use'], 403),
            self::API.'/api/instance/reconnect/escola' => Http::response(['base64' => 'QR-RECONNECT']),
        ]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/whatsapp/connect', ['instance_name' => 'escola'])
            ->assertOk()
            ->assertJsonPath('qr_code', 'QR-RECONNECT');
    }

    public function test_connect_com_api_offline_retorna_422(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/whatsapp/connect', ['instance_name' => 'escola'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Erro ao conectar com a API do WhatsApp.');

        $this->assertDatabaseCount('configuracoes_whatsapp', 0);
    }

    public function test_connect_com_erro_retorna_422(): void
    {
        Http::fake([
            self::API.'/api/instance/connect' => Http::response(['message' => 'Falha geral'], 500),
        ]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/whatsapp/connect', ['instance_name' => 'escola'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Falha geral');
    }

    public function test_reconnect_retorna_novo_qr_code(): void
    {
        $this->criarConfig('disconnected');

        Http::fake([
            self::API.'/api/instance/reconnect/escola' => Http::response(['qrcode' => ['base64' => 'QR-NOVO']]),
        ]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->putJson('/api/whatsapp/reconnect')
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('qr_code', 'QR-NOVO');
    }

    public function test_reconnect_sem_configuracao_retorna_404(): void
    {
        Http::fake();

        $this->comoUsuario($this->admin, $this->instituicao)
            ->putJson('/api/whatsapp/reconnect')
            ->assertNotFound();
    }

    public function test_disconnect_atualiza_status_mesmo_com_api_offline(): void
    {
        $config = $this->criarConfig('connected');

        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->comoUsuario($this->admin, $this->instituicao)
            ->deleteJson('/api/whatsapp/disconnect')
            ->assertOk()
            ->assertJsonPath('status', 'disconnected');

        $this->assertSame('disconnected', $config->fresh()->status);
    }

    private function criarDisparo(): NotificacaoDisparada
    {
        return $this->naInstituicao($this->instituicao, function (): NotificacaoDisparada {
            $config = ConfiguracaoNotificacao::create([
                'modulo' => 'contas_a_receber',
                'tipo' => 'atraso',
                'ativo' => true,
                'dias_antecedencia' => 1,
                'intervalo_repeticao' => 1,
                'template_mensagem' => 'x',
                'horario_envio' => '08:00',
            ]);

            return NotificacaoDisparada::create([
                'configuracao_notificacao_id' => $config->id,
                'referencia_type' => \App\Models\Conta::class,
                'referencia_id' => 1,
                'numero_whatsapp' => '5511988887777',
                'status' => 'pendente',
                'tentativas' => 0,
            ]);
        });
    }

    private function executarJob(NotificacaoDisparada $registro): void
    {
        $this->app->call([new EnviarMensagemWhatsappJob($registro->id, '5511988887777', 'Olá'), 'handle']);
    }

    public function test_job_envia_mensagem_e_marca_como_enviado(): void
    {
        $this->criarConfig('connected');
        $registro = $this->criarDisparo();

        Http::fake([
            self::API.'/api/message/text' => Http::response(['ok' => true]),
        ]);

        $this->executarJob($registro);

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/api/message/text'
            && $r['instance_name'] === 'escola'
            && $r['numbers'] === ['5511988887777']
            && $r['text'] === 'Olá');

        $registro = NotificacaoDisparada::withoutInstituicaoScope()->find($registro->id);
        $this->assertSame('enviado', $registro->status);
        $this->assertSame(1, (int) $registro->tentativas);
        $this->assertNotNull($registro->disparado_em);
    }

    public function test_job_marca_erro_quando_api_falha(): void
    {
        $this->criarConfig('connected');
        $registro = $this->criarDisparo();

        Http::fake([
            self::API.'/api/message/text' => Http::response(['erro' => 'x'], 500),
        ]);

        $this->executarJob($registro);

        $this->assertSame('erro', NotificacaoDisparada::withoutInstituicaoScope()->find($registro->id)->status);
    }

    public function test_job_relanca_excecao_quando_api_offline_para_nova_tentativa(): void
    {
        $this->criarConfig('connected');
        $registro = $this->criarDisparo();

        Http::fake(fn () => throw new ConnectionException('offline'));

        try {
            $this->executarJob($registro);
            $this->fail('A exceção deveria ser relançada para a fila tentar de novo.');
        } catch (WhatsappGatewayException $e) {
            $this->assertTrue($e->retryable);
        }

        $this->assertSame('erro', NotificacaoDisparada::withoutInstituicaoScope()->find($registro->id)->status);
    }

    public function test_job_marca_erro_quando_whatsapp_desconectado(): void
    {
        $this->criarConfig('disconnected');
        $registro = $this->criarDisparo();

        Http::fake();

        $this->executarJob($registro);

        Http::assertNothingSent();
        $this->assertSame('erro', NotificacaoDisparada::withoutInstituicaoScope()->find($registro->id)->status);
    }
}
