<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Instrumentos\Models\Instrumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstrumentosInstituicaoScopedTest extends TestCase
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

    public function test_instrumentos_sem_tenant_slug_retorna_422(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-inst@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->getJson('/api/instrumentos')
            ->assertStatus(422);
    }

    public function test_instrumentos_lista_apenas_da_instituicao_ativa(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-inst-b',
            'nome_fantasia' => 'Escola Inst B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-instrumentos@test.com');

        InstituicaoContext::setFromModel($default);
        Instrumento::create(['nome' => 'Violino Default', 'tipo' => 'cordas']);
        InstituicaoContext::clear();

        InstituicaoContext::setFromModel($outra);
        Instrumento::create(['nome' => 'Violino Outra', 'tipo' => 'cordas']);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/instrumentos');

        $response->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome');
        $this->assertTrue($nomes->contains('Violino Default'));
        $this->assertFalse($nomes->contains('Violino Outra'));
    }

    public function test_instrumento_de_outra_instituicao_retorna_404(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-inst-c',
            'nome_fantasia' => 'Escola Inst C',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-inst-show@test.com');

        InstituicaoContext::setFromModel($outra);
        $instrumentoOutra = Instrumento::create(['nome' => 'Flauta C', 'tipo' => 'sopro']);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson("/api/instrumentos/{$instrumentoOutra->id}")
            ->assertNotFound();
    }

    public function test_instrumento_criado_via_api_recebe_id_instituicao(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-inst-store@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->postJson('/api/instrumentos', [
                'nome' => 'Piano Novo',
                'tipo' => 'teclas',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('instrumentos', [
            'nome' => 'Piano Novo',
            'id_instituicao' => $instituicao->id,
        ]);
    }

    public function test_instrumento_ignora_campos_fora_da_validacao(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-inst-mass',
            'nome_fantasia' => 'Escola Inst Mass',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-inst-mass@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->postJson('/api/instrumentos', [
                'nome' => 'Flauta',
                'tipo' => 'sopro',
                'id_instituicao' => $outra->id,
                'data_emprestimo' => '2026-01-01',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('instrumentos', [
            'nome' => 'Flauta',
            'id_instituicao' => $instituicao->id,
            'data_emprestimo' => null,
        ]);
    }
}
