<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Pessoas\Models\Professor;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use App\Enums\TurmaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicoInstituicaoScopedTest extends TestCase
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

    public function test_cursos_sem_tenant_slug_retorna_422(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-acad@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->getJson('/api/cursos')
            ->assertStatus(422);
    }

    public function test_cursos_lista_apenas_da_instituicao_ativa(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-acad-b',
            'nome_fantasia' => 'Escola Acad B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-cursos@test.com');

        InstituicaoContext::setFromModel($default);
        $cursoDefault = Curso::create(['nome' => 'Curso Default', 'descricao' => 'A']);
        InstituicaoContext::clear();

        InstituicaoContext::setFromModel($outra);
        Curso::create(['nome' => 'Curso Outra', 'descricao' => 'B']);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/cursos');

        $response->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome');
        $this->assertTrue($nomes->contains('Curso Default'));
        $this->assertFalse($nomes->contains('Curso Outra'));
    }

    public function test_turma_de_outra_instituicao_retorna_404(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-acad-c',
            'nome_fantasia' => 'Escola Acad C',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-turma@test.com');

        InstituicaoContext::setFromModel($outra);
        $curso = Curso::create(['nome' => 'Curso C', 'descricao' => 'C']);
        $nivel = Nivel::create(['nome' => 'Nivel C']);
        $professor = Professor::create([
            'id_usuario' => User::factory()->create(['role' => 'professor'])->id,
            'comissao' => 10,
        ]);
        $turmaOutra = Turma::create([
            'id_instituicao' => $outra->id,
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::ABERTA,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => now()->toDateString(),
        ]);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson("/api/turmas/{$turmaOutra->id}")
            ->assertNotFound();
    }

    public function test_criar_curso_via_api_vincula_instituicao_ativa(): void
    {
        $instituicao = Instituicao::create([
            'slug' => 'escola-acad-create',
            'nome_fantasia' => 'Escola Acad Create',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-create-curso@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'escola-acad-create')
            ->postJson('/api/cursos', [
                'nome' => 'Novo Curso',
                'descricao' => 'Descrição',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('cursos', [
            'nome' => 'Novo Curso',
            'id_instituicao' => $instituicao->id,
        ]);
    }
}
