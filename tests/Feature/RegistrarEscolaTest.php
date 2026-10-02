<?php

namespace Tests\Feature;

use App\Modules\Core\Mail\CadastroEscolaMail;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrarEscolaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nome_fantasia' => 'Escola Harmonia',
            'cnpj' => $this->cnpjValido('escola-harmonia'),
            'responsavel_nome' => 'Maria Silva',
            'email' => 'maria@harmonia.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ], $overrides);
    }

    public function test_cria_escola_com_trial_padrao_configurado_e_pendente_de_ativacao(): void
    {
        Mail::fake();
        app(PlatformSettings::class)->set(PlatformSettings::DEFAULT_TRIAL_DAYS, '30');

        $response = $this->postJson('/api/registrar-escola', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('email_enviado', true)
            ->assertJsonPath('tenant.slug', 'escola-harmonia')
            ->assertJsonPath('user.email', 'maria@harmonia.com')
            ->assertJsonMissingPath('token');

        $instituicao = Instituicao::query()->where('slug', 'escola-harmonia')->firstOrFail();

        $this->assertSame(Instituicao::ASSINATURA_TRIALING, $instituicao->assinatura_status);
        $this->assertTrue($instituicao->trial_usa_padrao);
        $this->assertNull($instituicao->id_plano_assinatura);
        $this->assertSame(30, (int) round(now()->diffInDays($instituicao->trial_ends_at)));
        $this->assertTrue($instituicao->aguardandoAtivacao());
        $this->assertNotNull($instituicao->codigo_ativacao_expira_em);

        $user = User::query()->where('email', 'maria@harmonia.com')->firstOrFail();
        $this->assertSame('Maria Silva', $user->nome);
        $this->assertDatabaseHas('instituicoes_usuarios', [
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => 'a',
        ]);
    }

    public function test_envia_email_com_template_configurado_e_variaveis_substituidas(): void
    {
        Mail::fake();

        $settings = app(PlatformSettings::class);
        $settings->set(PlatformSettings::EMAIL_CADASTRO_ASSUNTO, 'Bem-vinda, {{escola_nome}}');
        $settings->set(
            PlatformSettings::EMAIL_CADASTRO_CORPO,
            '<p>Oi {{responsavel_nome}} ({{responsavel_email}}), CNPJ {{escola_cnpj}}, {{trial_dias}} dias. Código: {{codigo_ativacao}}</p>',
        );

        $this->postJson('/api/registrar-escola', $this->payload([
            'nome_fantasia' => 'Escola <Dó> & Ré',
        ]))->assertCreated();

        Mail::assertSent(CadastroEscolaMail::class, function (CadastroEscolaMail $mail): bool {
            $this->assertTrue($mail->hasTo('maria@harmonia.com'));
            $this->assertSame('Bem-vinda, Escola <Dó> & Ré', $mail->assunto);
            $this->assertMatchesRegularExpression('/^\d{6}$/', $mail->codigoAtivacao);
            $this->assertStringContainsString('Oi Maria Silva (maria@harmonia.com)', $mail->corpoHtml);
            $this->assertStringContainsString('14 dias', $mail->corpoHtml);
            $this->assertStringContainsString('Código: '.$mail->codigoAtivacao, $mail->corpoHtml);
            $this->assertStringNotContainsString('{{', $mail->corpoHtml);

            return true;
        });
    }

    public function test_valores_sao_escapados_no_corpo_html(): void
    {
        Mail::fake();

        app(PlatformSettings::class)->set(PlatformSettings::EMAIL_CADASTRO_CORPO, '<p>{{escola_nome}} {{codigo_ativacao}}</p>');

        $this->postJson('/api/registrar-escola', $this->payload([
            'nome_fantasia' => 'Escola <script>x</script>',
        ]))->assertCreated();

        Mail::assertSent(CadastroEscolaMail::class, function (CadastroEscolaMail $mail): bool {
            $this->assertStringContainsString('Escola &lt;script&gt;x&lt;/script&gt;', $mail->corpoHtml);

            return true;
        });
    }

    public function test_adiciona_codigo_quando_template_nao_tem_a_variavel(): void
    {
        Mail::fake();

        app(PlatformSettings::class)->set(PlatformSettings::EMAIL_CADASTRO_CORPO, '<p>Olá {{responsavel_nome}}</p>');

        $this->postJson('/api/registrar-escola', $this->payload())->assertCreated();

        Mail::assertSent(CadastroEscolaMail::class, function (CadastroEscolaMail $mail): bool {
            $this->assertStringContainsString($mail->codigoAtivacao, $mail->corpoHtml);

            return true;
        });
    }

    public function test_usa_template_padrao_quando_nao_configurado(): void
    {
        Mail::fake();

        $this->postJson('/api/registrar-escola', $this->payload())->assertCreated();

        Mail::assertSent(CadastroEscolaMail::class, function (CadastroEscolaMail $mail): bool {
            $this->assertStringContainsString('Escola Harmonia', $mail->assunto);
            $this->assertStringContainsString($mail->codigoAtivacao, $mail->corpoHtml);
            $this->assertStringContainsString('/login', $mail->corpoHtml);

            return true;
        });
    }

    public function test_gera_slug_unico_quando_nome_ja_existe(): void
    {
        Mail::fake();

        $this->postJson('/api/registrar-escola', $this->payload())->assertCreated();

        $this->postJson('/api/registrar-escola', $this->payload([
            'cnpj' => $this->cnpjValido('escola-harmonia-2'),
            'email' => 'outra@harmonia.com',
        ]))->assertCreated()
            ->assertJsonPath('tenant.slug', 'escola-harmonia-2');
    }

    public function test_rejeita_cnpj_duplicado(): void
    {
        Mail::fake();

        $this->postJson('/api/registrar-escola', $this->payload())->assertCreated();

        $this->postJson('/api/registrar-escola', $this->payload([
            'nome_fantasia' => 'Outra Escola',
            'email' => 'outra@harmonia.com',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj']);
    }

    public function test_exige_campos_obrigatorios(): void
    {
        $this->postJson('/api/registrar-escola', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'nome_fantasia',
                'cnpj',
                'responsavel_nome',
                'email',
                'password',
            ]);
    }
}
