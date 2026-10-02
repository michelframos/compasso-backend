<?php

namespace Tests\Feature;

use App\Models\Professor;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\User as CoreUser;
use App\Modules\Core\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaProfessorAutenticacaoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioProfessor;

    private Professor $professor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', [
            'senha' => Hash::make('SenhaAtual123'),
        ]);
        $this->professor = $this->naInstituicao(
            $this->instituicao,
            fn () => Professor::create(['id_usuario' => $this->usuarioProfessor->id, 'comissao' => 10])
        );
    }

    private function login(string $email, string $senha): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/login', [
            'email' => $email,
            'password' => $senha,
            'tenant_slug' => $this->instituicao->slug,
        ]);
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/professor/me')
            ->assertUnauthorized();
    }

    public function test_professor_da_instituicao_acessa_a_area(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson('/api/professor/me')
            ->assertOk()
            ->assertJsonPath('data.id', $this->professor->id)
            ->assertJsonPath('data.email', $this->usuarioProfessor->email);
    }

    public function test_outros_papeis_recebem_403(): void
    {
        foreach (['aluno', 'responsavel', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);

            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/professor/me')
                ->assertForbidden();
        }
    }

    public function test_professor_de_outra_instituicao_recebe_403(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'professor');
        $this->naInstituicao($outra, fn () => Professor::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/professor/me')
            ->assertForbidden();

        $this->comoUsuario($usuarioOutro, $outra)
            ->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/professor/me')
            ->assertForbidden();
    }

    public function test_professor_com_troca_de_senha_pendente_fica_bloqueado_ate_trocar(): void
    {
        $this->usuarioProfessor->update(['deve_trocar_senha' => true]);
        $outraSessao = $this->usuarioProfessor->createToken('outro-dispositivo');

        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson('/api/professor/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'troca_senha_obrigatoria');

        $this->getJson('/api/aulas-turmas')
            ->assertForbidden()
            ->assertJsonPath('code', 'troca_senha_obrigatoria');

        $this->putJson('/api/me/senha', [
            'senha_atual' => 'SenhaAtual123',
            'password' => 'NovaSenha456',
            'password_confirmation' => 'NovaSenha456',
        ])->assertOk()->assertJsonPath('data.deve_trocar_senha', false);

        $this->assertFalse($this->usuarioProfessor->fresh()->deve_trocar_senha);
        $this->assertTrue(Hash::check('NovaSenha456', $this->usuarioProfessor->fresh()->senha));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $outraSessao->accessToken->id]);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/professor/me')->assertOk();
    }

    public function test_troca_de_senha_exige_senha_atual_correta(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->putJson('/api/me/senha', [
                'senha_atual' => 'errada',
                'password' => 'NovaSenha456',
                'password_confirmation' => 'NovaSenha456',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['senha_atual']);
    }

    public function test_login_informa_troca_de_senha_pendente(): void
    {
        $this->usuarioProfessor->update(['deve_trocar_senha' => true]);

        $this->login($this->usuarioProfessor->email, 'SenhaAtual123')
            ->assertOk()
            ->assertJsonPath('data.deve_trocar_senha', true);
    }

    public function test_professor_criado_ou_com_senha_redefinida_pela_secretaria_deve_trocar_senha(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');

        $criado = $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/professores', [
                'nome' => 'Ana Lima',
                'email' => 'ana.lima@escola.test',
                'password' => 'Senha12345',
                'password_confirmation' => 'Senha12345',
                'cpf' => '529.982.247-25',
            ])
            ->assertCreated();

        $this->assertTrue(User::find($criado->json('id_usuario'))->deve_trocar_senha);

        $sessaoAtiva = $this->usuarioProfessor->createToken('sessao');

        $this->putJson("/api/professores/{$this->professor->id}", [
            'password' => 'OutraSenha123',
            'password_confirmation' => 'OutraSenha123',
        ])->assertOk();

        $this->assertTrue($this->usuarioProfessor->fresh()->deve_trocar_senha);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $sessaoAtiva->accessToken->id]);
    }

    public function test_professor_excluido_perde_tokens_e_nao_autentica(): void
    {
        $admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $tokenProfessor = $this->usuarioProfessor->createToken('sessao');
        $tokenProfessor->accessToken->update(['id_instituicao' => $this->instituicao->id]);

        $this->comoUsuario($admin, $this->instituicao)
            ->deleteJson("/api/professores/{$this->professor->id}")
            ->assertNoContent();

        $this->assertSame(0, DB::table('personal_access_tokens')
            ->where('tokenable_id', $this->usuarioProfessor->id)
            ->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($tokenProfessor->plainTextToken)
            ->getJson('/api/professor/me')
            ->assertUnauthorized();

        $this->login($this->usuarioProfessor->email, 'SenhaAtual123')->assertUnauthorized();
    }

    public function test_login_tem_limite_de_tentativas(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login($this->usuarioProfessor->email, 'senha-errada')->assertUnauthorized();
        }

        $this->login($this->usuarioProfessor->email, 'SenhaAtual123')->assertTooManyRequests();
    }

    public function test_secretaria_envia_link_de_acesso_ao_professor(): void
    {
        Notification::fake();
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson("/api/professores/{$this->professor->id}/enviar-acesso")
            ->assertOk();

        Notification::assertSentTo(
            CoreUser::find($this->usuarioProfessor->id),
            ResetPasswordNotification::class,
            fn (ResetPasswordNotification $notification) => $notification->conviteDeAcesso
        );
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $this->usuarioProfessor->email]);
    }

    public function test_professor_nao_envia_link_de_acesso(): void
    {
        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->postJson("/api/professores/{$this->professor->id}/enviar-acesso")
            ->assertForbidden();
    }

    public function test_redefinir_senha_pelo_codigo_conclui_troca_obrigatoria(): void
    {
        $this->usuarioProfessor->update(['deve_trocar_senha' => true]);
        DB::table('password_reset_tokens')->insert([
            'email' => $this->usuarioProfessor->email,
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/reset-password', [
            'email' => $this->usuarioProfessor->email,
            'token' => '123456',
            'password' => 'NovaSenha456',
            'password_confirmation' => 'NovaSenha456',
        ])->assertOk();

        $this->assertFalse($this->usuarioProfessor->fresh()->deve_trocar_senha);
    }
}
