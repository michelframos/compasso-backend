<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\SiteModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformSiteModuloTest extends TestCase
{
    use RefreshDatabase;

    private function criarSuperAdmin(): User
    {
        return User::factory()->create([
            'email' => 'super-modulo@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nome' => 'Financeiro',
            'descricao' => 'Contas e pagamentos da escola.',
            'recursos' => ['PIX', 'Contratos'],
            'icone' => 'Wallet',
            'ordem' => 1,
        ], $overrides);
    }

    public function test_usuario_comum_nao_acessa_platform_modulos(): void
    {
        $user = User::factory()->create([
            'email' => 'comum-modulo@test.com',
            'senha' => Hash::make('password'),
            'is_super_admin' => false,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/platform/modulos')
            ->assertForbidden();
    }

    public function test_super_admin_cria_modulo_pendente(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $this->postJson('/api/platform/modulos', $this->payload(['aprovado' => true]))
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Financeiro')
            ->assertJsonPath('data.aprovado', false)
            ->assertJsonPath('data.recursos.0', 'PIX');
    }

    public function test_super_admin_aceita_recursos_em_texto(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $this->postJson('/api/platform/modulos', [
            'nome' => 'Agenda',
            'descricao' => 'Aulas da semana.',
            'recursos' => "Horários\nPresença",
            'icone' => 'CalendarDays',
        ])
            ->assertCreated()
            ->assertJsonPath('data.recursos.0', 'Horários')
            ->assertJsonPath('data.recursos.1', 'Presença');
    }

    public function test_super_admin_atualiza_sem_publicar(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());
        $modulo = SiteModulo::query()->create($this->payload(['aprovado' => false]));

        $this->putJson("/api/platform/modulos/{$modulo->id}", [
            'descricao' => 'Texto revisado.',
            'aprovado' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.descricao', 'Texto revisado.')
            ->assertJsonPath('data.aprovado', false);
    }

    public function test_super_admin_aprova_e_oculta(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());
        $modulo = SiteModulo::query()->create($this->payload());

        $this->postJson("/api/platform/modulos/{$modulo->id}/aprovar")
            ->assertOk()
            ->assertJsonPath('data.aprovado', true);

        $this->postJson("/api/platform/modulos/{$modulo->id}/ocultar")
            ->assertOk()
            ->assertJsonPath('data.aprovado', false);
    }

    public function test_super_admin_exclui_modulo(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());
        $modulo = SiteModulo::query()->create($this->payload());

        $this->deleteJson("/api/platform/modulos/{$modulo->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('site_modulos', ['id' => $modulo->id]);
    }
}
