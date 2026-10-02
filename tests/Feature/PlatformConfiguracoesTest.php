<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformConfiguracoesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_super_admin' => true,
            'role' => 'admin',
        ]));
    }

    public function test_super_admin_le_configuracoes_com_valores_padrao(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/platform/configuracoes')
            ->assertOk()
            ->assertJsonPath('default_trial_days', 14)
            ->assertJsonPath('email_cadastro_assunto', PlatformSettings::defaultEmailCadastroAssunto())
            ->assertJsonPath('email_cadastro_corpo', PlatformSettings::defaultEmailCadastroCorpo())
            ->assertJsonStructure(['variaveis_email' => [['chave', 'descricao']]]);
    }

    public function test_super_admin_atualiza_configuracoes(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/platform/configuracoes', [
            'default_trial_days' => 45,
            'email_cadastro_assunto' => 'Olá {{escola_nome}}',
            'email_cadastro_corpo' => '<p>Código {{codigo_ativacao}}</p>',
        ])
            ->assertOk()
            ->assertJsonPath('default_trial_days', 45)
            ->assertJsonPath('email_cadastro_assunto', 'Olá {{escola_nome}}')
            ->assertJsonPath('email_cadastro_corpo', '<p>Código {{codigo_ativacao}}</p>');

        $this->getJson('/api/platform/config')->assertJsonPath('default_trial_days', 45);
        $this->getJson('/api/public/config')->assertJsonPath('default_trial_days', 45);
    }

    public function test_valida_dias_de_gratuidade(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/platform/configuracoes', ['default_trial_days' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['default_trial_days']);
    }

    public function test_usuario_comum_nao_acessa_configuracoes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_super_admin' => false,
            'role' => 'admin',
        ]));

        $this->getJson('/api/platform/configuracoes')->assertForbidden();
        $this->putJson('/api/platform/configuracoes', ['default_trial_days' => 30])->assertForbidden();
    }

    public function test_sem_valor_salvo_usa_config_do_env(): void
    {
        config(['platform.default_trial_days' => 21]);
        $this->actingAsSuperAdmin();

        $this->getJson('/api/platform/configuracoes')->assertJsonPath('default_trial_days', 21);
    }
}
