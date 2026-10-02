<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformInstituicaoUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function criarSuperAdmin(): User
    {
        return User::factory()->create([
            'email' => 'super-users@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
    }

    public function test_super_admin_lista_usuarios_da_instituicao(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $instituicao = Instituicao::where('slug', 'default')->first();

        $user = User::factory()->create(['email' => 'listado@test.com', 'nome' => 'Usuario Listado']);
        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'secretaria',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $response = $this->getJson("/api/platform/instituicoes/{$instituicao->id}/usuarios");

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'nome', 'email', 'role', 'membership_status']]]);

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertTrue($emails->contains('listado@test.com'));
    }

    public function test_lista_usuarios_com_busca(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $instituicao = Instituicao::where('slug', 'default')->first();

        $user = User::factory()->create(['email' => 'busca-unica@test.com', 'nome' => 'Nome Busca']);
        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $response = $this->getJson("/api/platform/instituicoes/{$instituicao->id}/usuarios?search=busca-unica");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('busca-unica@test.com', $response->json('data.0.email'));
    }

    public function test_usuario_comum_nao_lista_usuarios_platform(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_super_admin' => false]);
        Sanctum::actingAs($user);

        $instituicao = Instituicao::where('slug', 'default')->first();

        $this->getJson("/api/platform/instituicoes/{$instituicao->id}/usuarios")
            ->assertForbidden();
    }
}
