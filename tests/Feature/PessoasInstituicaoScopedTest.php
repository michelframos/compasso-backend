<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Aluno;
use App\Modules\Pessoas\Models\Professor;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PessoasInstituicaoScopedTest extends TestCase
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

    private function criarAlunoNaInstituicao(Instituicao $instituicao, string $suffix): Aluno
    {
        InstituicaoContext::setFromModel($instituicao);

        $alunoUser = User::factory()->create([
            'email' => "aluno-{$suffix}@test.com",
            'role' => 'aluno',
        ]);

        $aluno = Aluno::create([
            'id_usuario' => $alunoUser->id,
            'id_instituicao' => $instituicao->id,
        ]);

        InstituicaoContext::clear();

        return $aluno;
    }

    public function test_professores_sem_tenant_slug_retorna_422(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-pessoas@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->getJson('/api/professores')
            ->assertStatus(422);
    }

    public function test_professores_lista_apenas_da_instituicao_ativa(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-pessoas-b',
            'nome_fantasia' => 'Escola Pessoas B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-prof@test.com');

        InstituicaoContext::setFromModel($default);
        $profDefault = Professor::create([
            'id_usuario' => User::factory()->create(['role' => 'professor', 'email' => 'prof-default@test.com'])->id,
            'comissao' => 10,
        ]);
        InstituicaoContext::clear();

        InstituicaoContext::setFromModel($outra);
        Professor::create([
            'id_usuario' => User::factory()->create(['role' => 'professor', 'email' => 'prof-outra@test.com'])->id,
            'comissao' => 15,
        ]);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/professores');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($profDefault->id));
        $this->assertSame(1, $ids->count());
    }

    public function test_aluno_de_outra_instituicao_retorna_404(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-pessoas-c',
            'nome_fantasia' => 'Escola Pessoas C',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-aluno@test.com');
        $alunoOutra = $this->criarAlunoNaInstituicao($outra, 'outra');

        $token = $this->autenticarComo($admin, $default);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson("/api/alunos/{$alunoOutra->id}")
            ->assertNotFound();
    }

    public function test_criar_responsavel_fica_na_instituicao_ativa(): void
    {
        $instituicao = Instituicao::create([
            'slug' => 'escola-resp',
            'nome_fantasia' => 'Escola Resp',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-resp@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'escola-resp')
            ->postJson('/api/responsaveis', [
                'nome' => 'Responsável Teste',
                'email' => 'resp@test.com',
                'cpf' => '52998224725',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('responsaveis', [
            'id_instituicao' => $instituicao->id,
        ]);
    }

    public function test_resolve_or_create_responsavel_usa_instituicao_ativa(): void
    {
        $instituicao = Instituicao::create([
            'slug' => 'escola-resolve',
            'nome_fantasia' => 'Escola Resolve',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-resolve@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'escola-resolve')
            ->postJson('/api/alunos', [
                'nome' => 'Aluno Com Resp',
                'email' => 'aluno-resp@test.com',
                'cpf' => '39053344705',
                'responsavel_nome' => 'Pai do Aluno',
                'responsavel_email' => 'pai@test.com',
                'responsavel_cpf' => '11144477735',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('responsaveis', [
            'id_instituicao' => $instituicao->id,
        ]);

        $this->assertDatabaseHas('alunos', [
            'id_instituicao' => $instituicao->id,
        ]);
    }
}
