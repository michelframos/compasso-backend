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
use Tests\TestCase;

class AssinaturaSolicitacaoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{instituicao: Instituicao, user: User, token: string}
     */
    private function criarInstituicaoComAdmin(): array
    {
        $instituicao = Instituicao::query()->create([
            'slug' => 'escola-assinatura',
            'nome_fantasia' => 'Escola Assinatura',
            'status' => Instituicao::STATUS_ATIVO,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
            'trial_ends_at' => now()->addDays(10),
            'trial_usa_padrao' => true,
        ]);

        $user = User::factory()->create([
            'email' => 'admin@escola-assinatura.test',
            'senha' => Hash::make('password'),
            'role' => 'admin',
        ]);

        InstituicaoUsuario::query()->create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $token = $user->createToken('test');
        $token->accessToken->update(['id_instituicao' => $instituicao->id]);

        return [
            'instituicao' => $instituicao,
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }

    public function test_admin_lista_planos_ativos(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        ['token' => $token, 'instituicao' => $instituicao] = $this->criarInstituicaoComAdmin();

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', $instituicao->slug)
            ->getJson('/api/assinatura/planos')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.1.destaque', true);
    }

    public function test_admin_solicita_assinatura(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        ['token' => $token, 'user' => $user, 'instituicao' => $instituicao] = $this->criarInstituicaoComAdmin();
        $planoId = PlanoAssinatura::query()->where('slug', 'profissional')->value('id');

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', $instituicao->slug)
            ->postJson('/api/assinatura/solicitar', [
                'id_plano_assinatura' => $planoId,
                'observacao' => 'Quero o plano profissional',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', SolicitacaoAssinatura::STATUS_PENDENTE)
            ->assertJsonPath('data.plano.slug', 'profissional');

        $this->assertDatabaseHas('solicitacoes_assinatura', [
            'id_instituicao' => $instituicao->id,
            'id_plano_assinatura' => $planoId,
            'id_usuario' => $user->id,
            'status' => SolicitacaoAssinatura::STATUS_PENDENTE,
        ]);
    }

    public function test_nao_permite_segunda_solicitacao_pendente(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        ['token' => $token, 'instituicao' => $instituicao] = $this->criarInstituicaoComAdmin();
        $planoId = PlanoAssinatura::query()->where('slug', 'basico')->value('id');

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', $instituicao->slug)
            ->postJson('/api/assinatura/solicitar', [
                'id_plano_assinatura' => $planoId,
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', $instituicao->slug)
            ->postJson('/api/assinatura/solicitar', [
                'id_plano_assinatura' => $planoId,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_plano_assinatura']);
    }

    public function test_retorna_solicitacao_pendente_atual(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        ['token' => $token, 'instituicao' => $instituicao] = $this->criarInstituicaoComAdmin();
        $planoId = PlanoAssinatura::query()->where('slug', 'enterprise')->value('id');

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', $instituicao->slug)
            ->postJson('/api/assinatura/solicitar', [
                'id_plano_assinatura' => $planoId,
            ])
            ->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', $instituicao->slug)
            ->getJson('/api/assinatura/solicitacao')
            ->assertOk()
            ->assertJsonPath('data.status', 'pendente')
            ->assertJsonPath('data.plano.slug', 'enterprise');
    }

    public function test_exige_tenant_header(): void
    {
        $this->seed(PlanoAssinaturaSeeder::class);
        ['token' => $token] = $this->criarInstituicaoComAdmin();

        $this->withToken($token)
            ->postJson('/api/assinatura/solicitar', [
                'id_plano_assinatura' => 1,
            ])
            ->assertStatus(422);
    }
}
