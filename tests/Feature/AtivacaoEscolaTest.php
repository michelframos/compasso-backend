<?php

namespace Tests\Feature;

use App\Models\User as LegacyUser;
use App\Modules\Core\Mail\CadastroEscolaMail;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AtivacaoEscolaTest extends TestCase
{
    use RefreshDatabase;

    private string $cnpj;

    private string $codigo = '';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->cnpj = $this->cnpjValido('escola-ativacao');

        $this->postJson('/api/registrar-escola', [
            'nome_fantasia' => 'Escola Ativação',
            'cnpj' => $this->cnpj,
            'responsavel_nome' => 'João Souza',
            'email' => 'joao@ativacao.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertCreated();

        Mail::assertSent(CadastroEscolaMail::class, function (CadastroEscolaMail $mail): bool {
            $this->codigo = $mail->codigoAtivacao;

            return true;
        });
    }

    /**
     * @return array<string, string>
     */
    private function credenciais(array $extra = []): array
    {
        return array_merge([
            'email' => 'joao@ativacao.com',
            'password' => 'Password1!',
            'tenant_cnpj' => $this->cnpj,
        ], $extra);
    }

    private function instituicao(): Instituicao
    {
        return Instituicao::query()->where('cnpj', $this->cnpj)->firstOrFail();
    }

    public function test_login_sem_codigo_exige_ativacao(): void
    {
        $this->postJson('/api/login', $this->credenciais())
            ->assertForbidden()
            ->assertJsonPath('code', 'activation_required')
            ->assertJsonMissingPath('token');
    }

    public function test_login_com_senha_errada_nao_revela_ativacao(): void
    {
        $this->postJson('/api/login', $this->credenciais(['password' => 'errada123']))
            ->assertUnauthorized();
    }

    public function test_login_com_codigo_errado_retorna_erro(): void
    {
        $codigoErrado = $this->codigo === '000000' ? '111111' : '000000';

        $this->postJson('/api/login', $this->credenciais(['activation_code' => $codigoErrado]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['activation_code']);

        $this->assertTrue($this->instituicao()->aguardandoAtivacao());
    }

    public function test_login_com_codigo_expirado_retorna_erro(): void
    {
        $this->instituicao()->forceFill(['codigo_ativacao_expira_em' => now()->subMinute()])->save();

        $this->postJson('/api/login', $this->credenciais(['activation_code' => $this->codigo]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['activation_code']);
    }

    public function test_login_com_codigo_correto_ativa_e_retorna_token(): void
    {
        $this->postJson('/api/login', $this->credenciais(['activation_code' => $this->codigo]))
            ->assertOk()
            ->assertJsonStructure(['token', 'tenant', 'data']);

        $instituicao = $this->instituicao();
        $this->assertFalse($instituicao->aguardandoAtivacao());
        $this->assertNotNull($instituicao->ativada_em);
        $this->assertNull($instituicao->codigo_ativacao);

        $this->postJson('/api/login', $this->credenciais())
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_reenviar_codigo_gera_novo_codigo(): void
    {
        $codigoAntigo = $this->codigo;

        $this->postJson('/api/ativacao/reenviar', $this->credenciais())
            ->assertOk();

        Mail::assertSent(CadastroEscolaMail::class, 2);

        $novoCodigo = null;
        Mail::assertSent(CadastroEscolaMail::class, function (CadastroEscolaMail $mail) use (&$novoCodigo): bool {
            $novoCodigo = $mail->codigoAtivacao;

            return true;
        });

        $this->assertTrue(Hash::check($novoCodigo, $this->instituicao()->codigo_ativacao));

        if ($novoCodigo !== $codigoAntigo) {
            $this->postJson('/api/login', $this->credenciais(['activation_code' => $codigoAntigo]))
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', $this->credenciais(['activation_code' => $novoCodigo]))
            ->assertOk();
    }

    public function test_reenviar_codigo_com_credenciais_invalidas(): void
    {
        $this->postJson('/api/ativacao/reenviar', $this->credenciais(['password' => 'errada123']))
            ->assertUnauthorized();

        Mail::assertSent(CadastroEscolaMail::class, 1);
    }

    public function test_reenviar_codigo_para_escola_ja_ativada(): void
    {
        $this->postJson('/api/login', $this->credenciais(['activation_code' => $this->codigo]))->assertOk();

        $this->postJson('/api/ativacao/reenviar', $this->credenciais())
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_activated');
    }

    public function test_escola_existente_sem_codigo_loga_normalmente(): void
    {
        $user = LegacyUser::factory()->create([
            'email' => 'antigo@escola.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $instituicao = Instituicao::query()->where('slug', 'default')->firstOrFail();
        InstituicaoUsuario::query()->create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $this->postJson('/api/login', [
            'email' => 'antigo@escola.com',
            'password' => 'password',
            'tenant_slug' => 'default',
        ])->assertOk()
            ->assertJsonStructure(['token']);
    }
}
