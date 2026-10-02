<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Notificacoes\Jobs\EnviarMensagemWhatsappJob;
use App\Modules\Notificacoes\Models\ConfiguracaoWhatsapp;
use App\Modules\Notificacoes\Models\NotificacaoDisparada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class ContaNotificacaoControllerTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $admin;

    private Conta $conta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');

        $this->conta = $this->naInstituicao($this->instituicao, function (): Conta {
            $usuarioAluno = User::factory()->create([
                'nome' => 'Maria Aluna',
                'email' => 'maria@aluna.test',
                'whatsapp' => '(11) 98888-7777',
                'role' => 'aluno',
            ]);
            $aluno = Aluno::create(['id_usuario' => $usuarioAluno->id]);
            $categoria = CategoriaConta::create(['nome' => 'Mensalidades', 'tipo' => 'receita']);

            return Conta::create([
                'id_categoria' => $categoria->id,
                'id_aluno' => $aluno->id,
                'descricao' => 'Mensalidade',
                'valor' => 150,
                'data_vencimento' => now()->subDays(5)->toDateString(),
                'status' => 'pendente',
                'tipo' => 'receita',
            ]);
        });
    }

    public function test_preview_email_monta_destino_e_mensagem(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson("/api/notificacoes/contas/{$this->conta->id}/preview?canal=email")
            ->assertOk()
            ->assertJsonPath('canal', 'email')
            ->assertJsonPath('destino', 'maria@aluna.test')
            ->assertJsonPath('destinatario', 'Maria Aluna')
            ->assertJsonPath('mensagem', 'Olá Maria Aluna. Identificamos um atraso de 5 dias na sua parcela de R$ 150,00 com vencimento em '.now()->subDays(5)->format('d/m/Y').'.');
    }

    public function test_preview_whatsapp_formata_numero_com_ddi(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson("/api/notificacoes/contas/{$this->conta->id}/preview?canal=whatsapp")
            ->assertOk()
            ->assertJsonPath('destino', '5511988887777');
    }

    public function test_enviar_email_retorna_sucesso(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/notificacoes/contas/{$this->conta->id}/enviar", [
                'canal' => 'email',
                'mensagem' => 'Teste',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'E-mail enviado com sucesso.')
            ->assertJsonPath('destino', 'maria@aluna.test');
    }

    public function test_enviar_whatsapp_sem_conexao_retorna_422(): void
    {
        Queue::fake();

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/notificacoes/contas/{$this->conta->id}/enviar", [
                'canal' => 'whatsapp',
                'mensagem' => 'Teste',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'WhatsApp não está conectado. Conecte em Configurações antes de enviar.');

        Queue::assertNothingPushed();
    }

    public function test_enviar_whatsapp_conectado_enfileira_job_e_registra_disparo(): void
    {
        Queue::fake();

        $this->naInstituicao($this->instituicao, fn () => ConfiguracaoWhatsapp::create([
            'instance_name' => 'escola',
            'status' => 'connected',
        ]));

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/notificacoes/contas/{$this->conta->id}/enviar", [
                'canal' => 'whatsapp',
                'mensagem' => 'Olá!',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Mensagem de WhatsApp enfileirada com sucesso.')
            ->assertJsonPath('destino', '5511988887777');

        Queue::assertPushed(EnviarMensagemWhatsappJob::class, 1);

        $this->assertDatabaseHas('notificacoes_disparadas', [
            'referencia_type' => \App\Models\Conta::class,
            'referencia_id' => $this->conta->id,
            'numero_whatsapp' => '5511988887777',
            'status' => 'pendente',
            'id_instituicao' => $this->instituicao->id,
        ]);
        $this->assertSame(1, NotificacaoDisparada::withoutInstituicaoScope()->count());
    }

    public function test_destinatario_sem_contato_retorna_422(): void
    {
        $this->conta->aluno->usuario->update(['email' => null, 'whatsapp' => null, 'telefone' => null]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/notificacoes/contas/{$this->conta->id}/enviar", [
                'canal' => 'email',
                'mensagem' => 'Teste',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nenhum e-mail encontrado para o destinatário.');

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/notificacoes/contas/{$this->conta->id}/enviar", [
                'canal' => 'whatsapp',
                'mensagem' => 'Teste',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nenhum número de WhatsApp encontrado para o destinatário.');
    }

    public function test_canal_invalido_retorna_422(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/notificacoes/contas/{$this->conta->id}/enviar", [
                'canal' => 'sms',
                'mensagem' => 'Teste',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('canal');
    }
}
