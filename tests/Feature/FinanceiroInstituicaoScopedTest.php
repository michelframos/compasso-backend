<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\ConfiguracaoPix;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\Contrato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinanceiroInstituicaoScopedTest extends TestCase
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

    public function test_contas_sem_tenant_slug_retorna_422(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-fin@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->getJson('/api/contas')
            ->assertStatus(422);
    }

    public function test_categorias_lista_apenas_da_instituicao_ativa(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-fin-b',
            'nome_fantasia' => 'Escola Fin B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-cat@test.com');

        InstituicaoContext::setFromModel($default);
        CategoriaConta::create(['nome' => 'Cat Default', 'tipo' => 'receita']);
        InstituicaoContext::clear();

        InstituicaoContext::setFromModel($outra);
        CategoriaConta::create(['nome' => 'Cat Outra', 'tipo' => 'receita']);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/categorias-contas');

        $response->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome');
        $this->assertTrue($nomes->contains('Cat Default'));
        $this->assertFalse($nomes->contains('Cat Outra'));
    }

    public function test_conta_de_outra_instituicao_retorna_404(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-fin-c',
            'nome_fantasia' => 'Escola Fin C',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-conta@test.com');

        InstituicaoContext::setFromModel($outra);
        $categoria = CategoriaConta::create(['nome' => 'Cat C', 'tipo' => 'receita']);
        $contaOutra = Conta::create([
            'id_instituicao' => $outra->id,
            'id_categoria' => $categoria->id,
            'descricao' => 'Conta Outra',
            'valor' => 100,
            'data_vencimento' => now()->toDateString(),
            'tipo' => 'receita',
            'status' => 'pendente',
        ]);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson("/api/contas/{$contaOutra->id}")
            ->assertNotFound();
    }

    public function test_configuracao_pix_atualiza_instituicao_ativa(): void
    {
        $instituicao = Instituicao::create([
            'slug' => 'escola-pix',
            'nome_fantasia' => 'Escola Pix',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-pix@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'escola-pix')
            ->putJson('/api/configuracao-pix', [
                'chave_pix' => 'escola@test.com',
                'tipo_chave' => 'email',
                'nome_beneficiario' => 'Escola Pix',
                'cidade' => 'Sao Paulo',
                'exibir_qrcode' => true,
            ])
            ->assertOk()
            ->assertJsonPath('chave_pix', 'escola@test.com');

        $this->assertDatabaseHas('configuracoes_pix', [
            'id_instituicao' => $instituicao->id,
            'chave_pix' => 'escola@test.com',
        ]);

        $default = Instituicao::where('slug', 'default')->first();
        $this->assertDatabaseMissing('configuracoes_pix', [
            'id_instituicao' => $default->id,
            'chave_pix' => 'escola@test.com',
        ]);
    }

    public function test_criar_contrato_via_api_vincula_instituicao_ativa(): void
    {
        $instituicao = Instituicao::create([
            'slug' => 'escola-contrato',
            'nome_fantasia' => 'Escola Contrato',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-contrato@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'escola-contrato')
            ->postJson('/api/contratos', [
                'nome' => 'Contrato Teste',
                'conteudo' => 'Conteúdo do contrato',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('contratos', [
            'nome' => 'Contrato Teste',
            'id_instituicao' => $instituicao->id,
        ]);
    }
}
