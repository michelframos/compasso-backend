<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\User;
use App\Modules\Comercial\Models\Lead;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\CategoriaConta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RelatoriosInstituicaoScopedTest extends TestCase
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

    public function test_dashboard_sem_tenant_slug_retorna_422(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-rel@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->getJson('/api/dashboard/resumo')
            ->assertStatus(422);
    }

    public function test_dashboard_resumo_conta_apenas_leads_da_instituicao(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-rel-b',
            'nome_fantasia' => 'Escola Rel B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-dashboard@test.com');

        InstituicaoContext::setFromModel($default);
        Lead::create(['nome' => 'Lead Default', 'status' => 'novo']);
        InstituicaoContext::clear();

        InstituicaoContext::setFromModel($outra);
        Lead::create(['nome' => 'Lead Outra', 'status' => 'novo']);
        Lead::create(['nome' => 'Lead Outra 2', 'status' => 'contatado']);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/dashboard/resumo');

        $response->assertOk()
            ->assertJsonPath('leadsAtivos', 1);
    }

    public function test_relatorio_inadimplencia_nao_inclui_contas_de_outra_instituicao(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-rel-c',
            'nome_fantasia' => 'Escola Rel C',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-inad@test.com');

        $userDefault = User::factory()->create(['role' => 'aluno']);
        InstituicaoContext::setFromModel($default);
        $alunoDefault = Aluno::create(['id_usuario' => $userDefault->id, 'observacoes' => 'teste']);
        $categoriaDefault = CategoriaConta::create(['nome' => 'Cat Default', 'tipo' => 'receita']);
        Conta::create([
            'id_categoria' => $categoriaDefault->id,
            'descricao' => 'Conta Default',
            'valor' => 100,
            'data_vencimento' => now()->subDays(5)->toDateString(),
            'status' => 'pendente',
            'tipo' => 'receita',
            'id_aluno' => $alunoDefault->id,
        ]);
        InstituicaoContext::clear();

        InstituicaoContext::setFromModel($outra);
        $userOutra = User::factory()->create(['role' => 'aluno']);
        $alunoOutra = Aluno::create(['id_usuario' => $userOutra->id, 'observacoes' => 'teste']);
        $categoriaOutra = CategoriaConta::create(['nome' => 'Cat Outra', 'tipo' => 'receita']);
        Conta::create([
            'id_categoria' => $categoriaOutra->id,
            'descricao' => 'Conta Outra',
            'valor' => 200,
            'data_vencimento' => now()->subDays(3)->toDateString(),
            'status' => 'pendente',
            'tipo' => 'receita',
            'id_aluno' => $alunoOutra->id,
        ]);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/relatorios/financeiro/inadimplencia');

        $response->assertOk();

        $descricoes = collect($response->json('data'))->pluck('descricao');
        $this->assertTrue($descricoes->contains('Conta Default'));
        $this->assertFalse($descricoes->contains('Conta Outra'));
    }
}
