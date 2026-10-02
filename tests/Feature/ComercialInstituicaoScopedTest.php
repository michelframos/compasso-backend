<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Comercial\Models\Lead;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ComercialInstituicaoScopedTest extends TestCase
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

    public function test_lead_publico_por_slug_cria_na_instituicao(): void
    {
        $instituicao = Instituicao::create([
            'slug' => 'escola-lead-public',
            'nome_fantasia' => 'Escola Lead Public',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $this->postJson('/api/leads/public/escola-lead-public', [
            'nome' => 'Lead Público',
            'email' => 'lead@public.com',
            'telefone' => '11999999999',
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Lead Público');

        $this->assertDatabaseHas('leads', [
            'nome' => 'Lead Público',
            'email' => 'lead@public.com',
            'id_instituicao' => $instituicao->id,
        ]);
    }

    public function test_lead_publico_com_slug_inexistente_retorna_404(): void
    {
        $this->postJson('/api/leads/public/nao-existe', [
            'nome' => 'Lead Inválido',
        ])->assertNotFound();
    }

    public function test_leads_internos_sem_tenant_slug_retorna_422(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();
        $admin = $this->criarAdminNaInstituicao($instituicao, 'admin-lead@test.com');
        $token = $this->autenticarComo($admin, $instituicao);

        $this->withToken($token)
            ->getJson('/api/leads')
            ->assertStatus(422);
    }

    public function test_leads_lista_apenas_da_instituicao_ativa(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-lead-b',
            'nome_fantasia' => 'Escola Lead B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-leads@test.com');

        InstituicaoContext::setFromModel($default);
        Lead::create(['nome' => 'Lead Default', 'email' => 'default@test.com']);
        InstituicaoContext::clear();

        InstituicaoContext::setFromModel($outra);
        Lead::create(['nome' => 'Lead Outra', 'email' => 'outra@test.com']);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $response = $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/leads');

        $response->assertOk();

        $nomes = collect($response->json('data'))->pluck('nome');
        $this->assertTrue($nomes->contains('Lead Default'));
        $this->assertFalse($nomes->contains('Lead Outra'));
    }

    public function test_lead_de_outra_instituicao_retorna_404(): void
    {
        $default = Instituicao::where('slug', 'default')->first();
        $outra = Instituicao::create([
            'slug' => 'escola-lead-c',
            'nome_fantasia' => 'Escola Lead C',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $admin = $this->criarAdminNaInstituicao($default, 'admin-lead-show@test.com');

        InstituicaoContext::setFromModel($outra);
        $leadOutra = Lead::create(['nome' => 'Lead C', 'email' => 'c@test.com']);
        InstituicaoContext::clear();

        $token = $this->autenticarComo($admin, $default);

        $this->withToken($token)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson("/api/leads/{$leadOutra->id}")
            ->assertNotFound();
    }
}
