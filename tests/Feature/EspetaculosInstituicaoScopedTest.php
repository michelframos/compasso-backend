<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Espetaculos\Models\Espetaculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EspetaculosInstituicaoScopedTest extends TestCase
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

    public function test_espetaculos_sem_tenant_slug_retorna_422(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-esp@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->getJson('/api/espetaculos')
            ->assertStatus(422);
    }

    public function test_espetaculos_lista_apenas_da_instituicao_ativa(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-esp-b',
            'nome_fantasia' => 'Escola Esp B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-espetaculos@test.com');

        InstituicaoContext::setFromModel($default);
        Espetaculo::create([
            'titulo' => 'Espetáculo Default',
            'data_evento' => '2026-12-01',
        ]);
        InstituicaoContext::clear();

        InstituicaoContext::setFromModel($outra);
        Espetaculo::create([
            'titulo' => 'Espetáculo Outra',
            'data_evento' => '2026-12-15',
        ]);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/espetaculos');

        $response->assertOk();

        $titulos = collect($response->json('data'))->pluck('titulo');
        $this->assertTrue($titulos->contains('Espetáculo Default'));
        $this->assertFalse($titulos->contains('Espetáculo Outra'));
    }

    public function test_espetaculo_de_outra_instituicao_retorna_404(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-esp-c',
            'nome_fantasia' => 'Escola Esp C',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-esp-show@test.com');

        InstituicaoContext::setFromModel($outra);
        $espetaculoOutra = Espetaculo::create([
            'titulo' => 'Espetáculo C',
            'data_evento' => '2026-11-01',
        ]);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson("/api/espetaculos/{$espetaculoOutra->id}")
            ->assertNotFound();
    }

    public function test_espetaculo_criado_via_api_recebe_id_instituicao(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-esp-store@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->postJson('/api/espetaculos', [
                'titulo' => 'Novo Espetáculo',
                'data_evento' => '2026-10-20',
            ])
            ->assertCreated()
            ->assertJsonPath('data.titulo', 'Novo Espetáculo');

        $this->assertDatabaseHas('espetaculos', [
            'titulo' => 'Novo Espetáculo',
            'id_instituicao' => $instituicao->id,
        ]);
    }
}
