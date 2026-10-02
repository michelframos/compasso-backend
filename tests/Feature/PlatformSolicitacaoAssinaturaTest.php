<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Models\SolicitacaoAssinatura;
use Database\Seeders\PlanoAssinaturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformSolicitacaoAssinaturaTest extends TestCase
{
    use RefreshDatabase;

    private function criarSuperAdmin(): User
    {
        return User::factory()->create([
            'email' => 'super-sol@test.com',
            'senha' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
        ]);
    }

    /**
     * @return array{instituicao: Instituicao, user: User, solicitacao: SolicitacaoAssinatura, plano: PlanoAssinatura}
     */
    private function criarSolicitacaoPendente(): array
    {
        $this->seed(PlanoAssinaturaSeeder::class);

        $plano = PlanoAssinatura::query()->where('slug', 'profissional')->firstOrFail();

        $instituicao = Instituicao::query()->create([
            'slug' => 'escola-solicitacao',
            'nome_fantasia' => 'Escola Solicitação',
            'status' => Instituicao::STATUS_ATIVO,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
            'trial_ends_at' => now()->addDays(5),
            'trial_usa_padrao' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'admin@escola-solicitacao.test',
            'senha' => Hash::make('password'),
            'role' => 'admin',
        ]);

        InstituicaoUsuario::query()->create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $solicitacao = SolicitacaoAssinatura::query()->create([
            'id_instituicao' => $instituicao->id,
            'id_plano_assinatura' => $plano->id,
            'id_usuario' => $user->id,
            'status' => SolicitacaoAssinatura::STATUS_PENDENTE,
            'observacao' => 'Quero o profissional',
        ]);

        return compact('instituicao', 'user', 'solicitacao', 'plano');
    }

    public function test_super_admin_lista_solicitacoes(): void
    {
        $this->criarSolicitacaoPendente();
        Sanctum::actingAs($this->criarSuperAdmin());

        $this->getJson('/api/platform/solicitacoes?status=pendente')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pendente')
            ->assertJsonPath('data.0.instituicao.slug', 'escola-solicitacao')
            ->assertJsonPath('data.0.plano.slug', 'profissional');
    }

    public function test_aprova_solicitacao_e_ativa_plano(): void
    {
        ['instituicao' => $instituicao, 'solicitacao' => $solicitacao, 'plano' => $plano] = $this->criarSolicitacaoPendente();
        $super = $this->criarSuperAdmin();
        Sanctum::actingAs($super);

        $this->postJson("/api/platform/solicitacoes/{$solicitacao->id}/aprovar")
            ->assertOk()
            ->assertJsonPath('data.status', 'aprovada')
            ->assertJsonPath('data.aprovado_por.email', $super->email);

        $this->assertDatabaseHas('solicitacoes_assinatura', [
            'id' => $solicitacao->id,
            'status' => SolicitacaoAssinatura::STATUS_APROVADA,
            'id_aprovado_por' => $super->id,
        ]);

        $this->assertDatabaseHas('instituicoes', [
            'id' => $instituicao->id,
            'id_plano_assinatura' => $plano->id,
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
        ]);
    }

    public function test_rejeita_solicitacao(): void
    {
        ['solicitacao' => $solicitacao, 'instituicao' => $instituicao] = $this->criarSolicitacaoPendente();
        $super = $this->criarSuperAdmin();
        Sanctum::actingAs($super);

        $this->postJson("/api/platform/solicitacoes/{$solicitacao->id}/rejeitar", [
            'observacao' => 'Documentação incompleta',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejeitada');

        $this->assertDatabaseHas('solicitacoes_assinatura', [
            'id' => $solicitacao->id,
            'status' => SolicitacaoAssinatura::STATUS_REJEITADA,
            'observacao' => 'Documentação incompleta',
        ]);

        $this->assertDatabaseHas('instituicoes', [
            'id' => $instituicao->id,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
        ]);
    }

    public function test_nao_aprova_duas_vezes(): void
    {
        ['solicitacao' => $solicitacao] = $this->criarSolicitacaoPendente();
        Sanctum::actingAs($this->criarSuperAdmin());

        $this->postJson("/api/platform/solicitacoes/{$solicitacao->id}/aprovar")->assertOk();

        $this->postJson("/api/platform/solicitacoes/{$solicitacao->id}/aprovar")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_usuario_comum_nao_acessa(): void
    {
        $this->criarSolicitacaoPendente();

        $comum = User::factory()->create([
            'email' => 'comum-sol@test.com',
            'role' => 'admin',
            'is_super_admin' => false,
        ]);

        Sanctum::actingAs($comum);

        $this->getJson('/api/platform/solicitacoes')->assertForbidden();
    }
}
