<?php

namespace Tests\Feature;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Models\User;
use Database\Seeders\PlanoAssinaturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_planos_ativos_sem_auth(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);

        PlanoAssinatura::query()->create([
            'slug' => 'inativo',
            'nome' => 'Inativo',
            'descricao' => 'Não deve aparecer',
            'preco_mensal' => 10,
            'limite_alunos' => 10,
            'ativo' => false,
        ]);

        $response = $this->getJson('/api/public/planos');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'basico')
            ->assertJsonPath('data.0.preco', 99.9)
            ->assertJsonPath('data.0.features.0', 'Até 80 alunos')
            ->assertJsonPath('data.0.features.1', 'Leads')
            ->assertJsonPath('data.1.slug', 'profissional')
            ->assertJsonPath('data.1.destaque', true)
            ->assertJsonPath('data.2.slug', 'enterprise')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'slug',
                    'nome',
                    'preco',
                    'limite_alunos',
                    'features',
                    'destaque',
                ]],
            ]);
    }

    public function test_lista_planos_reflete_cadastro_do_admin(): void
    {
        PlanoAssinatura::query()->create([
            'slug' => 'starter',
            'nome' => 'Starter Escola',
            'descricao' => "Agenda completa\nRelatórios da operação",
            'preco_mensal' => 49.5,
            'limite_alunos' => 30,
            'ativo' => true,
        ]);

        PlanoAssinatura::query()->create([
            'slug' => 'completo',
            'nome' => 'Completo',
            'descricao' => 'Suporte prioritário',
            'preco_mensal' => 149,
            'limite_alunos' => null,
            'ativo' => true,
        ]);

        $this->getJson('/api/public/planos')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nome', 'Starter Escola')
            ->assertJsonPath('data.0.preco', 49.5)
            ->assertJsonPath('data.0.limite_alunos', 30)
            ->assertJsonPath('data.0.features.0', 'Até 30 alunos')
            ->assertJsonPath('data.0.features.1', 'Agenda completa')
            ->assertJsonPath('data.1.nome', 'Completo')
            ->assertJsonPath('data.1.features.0', 'Alunos ilimitados')
            ->assertJsonPath('data.1.destaque', true);
    }

    public function test_retorna_config_publica(): void
    {
        $response = $this->getJson('/api/public/config');

        $response->assertOk()
            ->assertJsonStructure(['default_trial_days'])
            ->assertJsonPath('default_trial_days', 14);
    }

    public function test_signup_cria_instituicao_admin_e_retorna_token(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);

        $response = $this->postJson('/api/public/signup', [
            'slug' => 'escola-trial',
            'nome_fantasia' => 'Escola Trial',
            'cnpj' => $this->cnpjValido('escola-trial'),
            'admin_nome' => 'Maria Silva',
            'admin_email' => 'maria@escola-trial.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
            'plano_slug' => 'profissional',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'nome', 'email'],
                'tenant' => ['id', 'slug', 'nome_fantasia'],
            ])
            ->assertJsonPath('user.email', 'maria@escola-trial.com')
            ->assertJsonPath('tenant.slug', 'escola-trial');

        $this->assertNotEmpty($response->json('token'));

        $this->assertDatabaseHas('instituicoes', [
            'slug' => 'escola-trial',
            'nome_fantasia' => 'Escola Trial',
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $plano = PlanoAssinatura::query()->where('slug', 'profissional')->first();
        $this->assertDatabaseHas('instituicoes', [
            'slug' => 'escola-trial',
            'id_plano_assinatura' => $plano->id,
        ]);

        $this->assertDatabaseHas('usuarios', [
            'email' => 'maria@escola-trial.com',
            'role' => 'admin',
        ]);

        $user = User::query()->where('email', 'maria@escola-trial.com')->first();
        $instituicao = Instituicao::query()->where('slug', 'escola-trial')->first();

        $this->assertDatabaseHas('instituicoes_usuarios', [
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => 'a',
        ]);

        $this->assertNotNull($instituicao->trial_ends_at);
    }

    public function test_signup_rejeita_honeypot_preenchido(): void
    {
        $response = $this->postJson('/api/public/signup', [
            'slug' => 'escola-bot',
            'nome_fantasia' => 'Escola Bot',
            'cnpj' => $this->cnpjValido('escola-bot'),
            'admin_nome' => 'Bot',
            'admin_email' => 'bot@escola.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
            'website' => 'http://spam.example',
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('instituicoes', [
            'slug' => 'escola-bot',
        ]);
    }

    public function test_signup_valida_slug_unico(): void
    {
        $this->postJson('/api/public/signup', [
            'slug' => 'escola-dup',
            'nome_fantasia' => 'Escola A',
            'cnpj' => $this->cnpjValido('escola-dup-a'),
            'admin_nome' => 'Admin A',
            'admin_email' => 'a@escola.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ])->assertCreated();

        $this->postJson('/api/public/signup', [
            'slug' => 'escola-dup',
            'nome_fantasia' => 'Escola B',
            'cnpj' => $this->cnpjValido('escola-dup-b'),
            'admin_nome' => 'Admin B',
            'admin_email' => 'b@escola.com',
            'admin_password' => 'Password1!',
            'admin_password_confirmation' => 'Password1!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_signup_exige_campos_obrigatorios(): void
    {
        $this->postJson('/api/public/signup', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
                'nome_fantasia',
                'cnpj',
                'admin_nome',
                'admin_email',
                'admin_password',
            ]);
    }
}
