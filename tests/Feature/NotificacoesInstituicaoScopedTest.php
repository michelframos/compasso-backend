<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Notificacoes\Models\ConfiguracaoNotificacao;
use App\Modules\Notificacoes\Models\ConfiguracaoWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificacoesInstituicaoScopedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        InstituicaoContext::clear();

        parent::tearDown();
    }

    private function criarAdminNaInstituicao(Instituicao $instituicao, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'senha' => Hash::make('password'),
            'role' => 'admin',
        ]);

        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        return $user;
    }

    private function autenticarComo(User $user, Instituicao $instituicao): string
    {
        $token = $user->createToken('test');
        $token->accessToken->update(['id_instituicao' => $instituicao->id]);

        return $token->plainTextToken;
    }

    public function test_whatsapp_config_sem_tenant_slug_retorna_422(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-notif@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->getJson('/api/whatsapp/config')
            ->assertStatus(422);
    }

    public function test_notificacoes_config_lista_apenas_da_instituicao_ativa(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-notif-b',
            'nome_fantasia' => 'Escola Notif B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-notif-config@test.com');

        InstituicaoContext::setFromModel($default);
        ConfiguracaoNotificacao::create([
            'modulo' => 'agendamentos',
            'ativo' => true,
            'dias_antecedencia' => 1,
            'intervalo_repeticao' => 1,
            'template_mensagem' => 'Default',
            'horario_envio' => '08:00',
        ]);
        InstituicaoContext::clear();

        InstituicaoContext::setFromModel($outra);
        ConfiguracaoNotificacao::create([
            'modulo' => 'agendamentos',
            'ativo' => true,
            'dias_antecedencia' => 2,
            'intervalo_repeticao' => 1,
            'template_mensagem' => 'Outra',
            'horario_envio' => '09:00',
        ]);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/notificacoes/config');

        $response->assertOk();

        $templates = collect($response->json())->pluck('template_mensagem');
        $this->assertTrue($templates->contains('Default'));
        $this->assertFalse($templates->contains('Outra'));
    }

    public function test_whatsapp_config_nao_exibe_instancia_de_outra_instituicao(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-notif-c',
            'nome_fantasia' => 'Escola Notif C',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-whatsapp@test.com');

        InstituicaoContext::setFromModel($outra);
        ConfiguracaoWhatsapp::create([
            'instance_name' => 'instancia-outra',
            'status' => 'connected',
        ]);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/whatsapp/config')
            ->assertOk()
            ->assertJsonPath('instance_name', null)
            ->assertJsonPath('status', 'disconnected');
    }

    public function test_configuracao_whatsapp_criada_recebe_id_instituicao(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();

        InstituicaoContext::setFromModel($instituicao);
        ConfiguracaoWhatsapp::create([
            'instance_name' => 'instancia-default',
            'status' => 'pending',
        ]);
        InstituicaoContext::clear();

        $this->assertDatabaseHas('configuracoes_whatsapp', [
            'instance_name' => 'instancia-default',
            'id_instituicao' => $instituicao->id,
        ]);
    }
}
