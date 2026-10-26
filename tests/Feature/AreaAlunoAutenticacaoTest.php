<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\Models\User as CoreUser;
use App\Modules\Core\Notifications\ResetPasswordNotification;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoAutenticacaoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAluno;

    private Aluno $aluno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', [
            'nome' => 'Ana Lima',
            'email' => 'ana.aluno@escola.test',
            'senha' => Hash::make('SenhaAtual123'),
        ]);
        $this->aluno = $this->naInstituicao(
            $this->instituicao,
            fn () => Aluno::create(['id_usuario' => $this->usuarioAluno->id])
        );
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me')
            ->assertUnauthorized();
    }

    public function test_aluno_da_instituicao_acessa_a_area(): void
    {
        $this->comoUsuario($this->usuarioAluno, $this->instituicao)
            ->getJson('/api/aluno/me')
            ->assertOk()
            ->assertJsonPath('data.id', $this->aluno->id)
            ->assertJsonPath('data.nome', 'Ana Lima')
            ->assertJsonMissingPath('data.observacoes');
    }

    public function test_outros_papeis_recebem_403(): void
    {
        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);

            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me')
                ->assertForbidden();
        }
    }

    public function test_aluno_de_outra_instituicao_recebe_403(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'aluno', ['nome' => 'Aluno de Fora']);
        $this->naInstituicao($outra, fn () => Aluno::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/aluno/me')
            ->assertForbidden();

        $this->comoUsuario($usuarioOutro, $outra)
            ->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me')
            ->assertForbidden();
    }

    public function test_troca_de_senha_pendente_bloqueia_a_area(): void
    {
        $this->usuarioAluno->update(['deve_trocar_senha' => true]);

        $this->comoUsuario($this->usuarioAluno, $this->instituicao)
            ->getJson('/api/aluno/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'troca_senha_obrigatoria');
    }

    public function test_cadastro_gera_senha_aleatoria_e_exige_troca(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');

        $criado = $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/alunos', [
                'nome' => 'Bruno Melo',
                'email' => 'bruno.aluno@escola.test',
                'responsavel_nome' => 'Rita Lima',
                'responsavel_email' => 'rita.responsavel@escola.test',
            ])
            ->assertCreated();

        $usuario = User::find($criado->json('id_usuario'));
        $this->assertTrue($usuario->deve_trocar_senha);
        $this->assertFalse(Hash::check('camerata123', $usuario->senha));
        $this->assertNotEmpty($usuario->senha);

        $responsavel = Responsavel::query()->whereHas('usuario', fn ($q) => $q->where('email', 'rita.responsavel@escola.test'))->first();
        $this->assertNotNull($responsavel);
        $this->assertTrue($responsavel->usuario->deve_trocar_senha);
        $this->assertFalse(Hash::check('camerata123', $responsavel->usuario->senha));
    }

    public function test_secretaria_envia_link_de_acesso_e_aluno_nao_envia(): void
    {
        Notification::fake();
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson("/api/alunos/{$this->aluno->id}/enviar-acesso")
            ->assertOk();

        Notification::assertSentTo(
            CoreUser::find($this->usuarioAluno->id),
            ResetPasswordNotification::class,
            fn (ResetPasswordNotification $notification) => $notification->conviteDeAcesso
        );

        $this->comoUsuario($this->usuarioAluno, $this->instituicao)
            ->postJson("/api/alunos/{$this->aluno->id}/enviar-acesso")
            ->assertForbidden();
    }

    public function test_responsavel_so_consulta_dependente_vinculado(): void
    {
        $usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $rita = $this->naInstituicao($this->instituicao, function () use ($usuarioRita) {
            $rita = Responsavel::create(['id_usuario' => $usuarioRita->id]);
            $this->aluno->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            return $rita;
        });

        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $bruno = $this->naInstituicao($this->instituicao, fn () => Aluno::create(['id_usuario' => $usuarioBruno->id]));

        $this->comoUsuario($usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_aluno');

        $this->getJson('/api/aluno/me?id_aluno='.$this->aluno->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->aluno->id)
            ->assertJsonPath('data.nome', 'Ana Lima');

        $this->getJson('/api/aluno/me?id_aluno='.$bruno->id)->assertForbidden();

        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $this->comoUsuario($secretaria, $this->instituicao)
            ->deleteJson("/api/alunos/{$this->aluno->id}/responsaveis/{$rita->id}")
            ->assertOk();

        $this->comoUsuario($usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me?id_aluno='.$this->aluno->id)
            ->assertForbidden();
    }

    public function test_aluno_excluido_perde_tokens_e_nao_autentica(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $token = $this->usuarioAluno->createToken('sessao');
        $token->accessToken->update(['id_instituicao' => $this->instituicao->id]);

        $this->comoUsuario($secretaria, $this->instituicao)
            ->deleteJson("/api/alunos/{$this->aluno->id}")
            ->assertNoContent();

        $this->assertSame(0, DB::table('personal_access_tokens')
            ->where('tokenable_id', $this->usuarioAluno->id)
            ->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)
            ->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me')
            ->assertUnauthorized();

        $this->postJson('/api/login', [
            'email' => $this->usuarioAluno->email,
            'password' => 'SenhaAtual123',
            'tenant_slug' => $this->instituicao->slug,
        ])->assertUnauthorized();
    }
}
