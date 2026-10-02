<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Depoimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformDepoimentoTest extends TestCase
{
    use RefreshDatabase;

    private function criarSuperAdmin(): User
    {
        return User::factory()->create([
            'email' => 'super-depoimento@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
    }

    private function criarUsuarioComum(): User
    {
        return User::factory()->create([
            'email' => 'comum-depoimento@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nome' => 'Ana Costa',
            'cargo' => 'Diretora',
            'escola' => 'Escola Nova',
            'conteudo' => 'O Compasso organizou nossa rotina administrativa.',
            'ordem' => 1,
        ], $overrides);
    }

    public function test_usuario_comum_nao_acessa_platform_depoimentos(): void
    {
        Sanctum::actingAs($this->criarUsuarioComum());

        $this->getJson('/api/platform/depoimentos')
            ->assertForbidden()
            ->assertJsonPath('message', 'Acesso restrito à plataforma.');
    }

    public function test_super_admin_cria_depoimento_pendente(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $this->postJson('/api/platform/depoimentos', $this->payload([
            'aprovado' => true,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Ana Costa')
            ->assertJsonPath('data.aprovado', false)
            ->assertJsonPath('data.aprovado_em', null);

        $this->assertDatabaseHas('depoimentos', [
            'nome' => 'Ana Costa',
            'aprovado' => false,
        ]);
    }

    public function test_super_admin_lista_e_filtra_aprovados(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        Depoimento::query()->create($this->payload([
            'nome' => 'Pendente',
            'aprovado' => false,
        ]));
        Depoimento::query()->create($this->payload([
            'nome' => 'Publicado',
            'aprovado' => true,
            'aprovado_em' => now(),
        ]));

        $this->getJson('/api/platform/depoimentos?aprovado=1')
            ->assertOk();

        $nomes = collect($this->getJson('/api/platform/depoimentos?aprovado=1')->json('data'))->pluck('nome');
        $this->assertTrue($nomes->contains('Publicado'));
        $this->assertFalse($nomes->contains('Pendente'));
    }

    public function test_super_admin_atualiza_sem_publicar(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $depoimento = Depoimento::query()->create($this->payload(['aprovado' => false]));

        $this->putJson("/api/platform/depoimentos/{$depoimento->id}", [
            'conteudo' => 'Texto revisado.',
            'aprovado' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.conteudo', 'Texto revisado.')
            ->assertJsonPath('data.aprovado', false);
    }

    public function test_super_admin_aprova_e_oculta(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $depoimento = Depoimento::query()->create($this->payload());

        $this->postJson("/api/platform/depoimentos/{$depoimento->id}/aprovar")
            ->assertOk()
            ->assertJsonPath('data.aprovado', true);

        $this->assertNotNull($depoimento->fresh()->aprovado_em);

        $this->postJson("/api/platform/depoimentos/{$depoimento->id}/ocultar")
            ->assertOk()
            ->assertJsonPath('data.aprovado', false)
            ->assertJsonPath('data.aprovado_em', null);
    }

    public function test_super_admin_exclui_depoimento(): void
    {
        Sanctum::actingAs($this->criarSuperAdmin());

        $depoimento = Depoimento::query()->create($this->payload());

        $this->deleteJson("/api/platform/depoimentos/{$depoimento->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('depoimentos', ['id' => $depoimento->id]);
    }
}
