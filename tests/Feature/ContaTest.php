<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Conta;
use App\Models\CategoriaConta;
use App\Models\User;

class ContaTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    public function test_conta_can_be_created_with_mensalidade_fields(): void
    {
        $categoria = CategoriaConta::create([
            'nome' => 'Mensalidade Teste',
            'tipo' => 'receita',
            'descricao' => 'Categoria de teste'
        ]);

        $conta = Conta::create([
            'id_categoria' => $categoria->id,
            'descricao' => 'Mensalidade de Março',
            'valor' => 150.00,
            'data_vencimento' => '2026-03-10',
            'tipo' => 'receita',
            'numero_parcela' => 2,
            'quantidade_parcelas' => 12,
            'mes_referencia' => 3,
            'ano_referencia' => 2026
        ]);

        $this->assertDatabaseHas('contas', [
            'id' => $conta->id,
            'numero_parcela' => 2,
            'quantidade_parcelas' => 12,
            'mes_referencia' => 3,
            'ano_referencia' => 2026
        ]);
    }

    public function test_can_list_contas()
    {
        $categoria = CategoriaConta::create(['nome' => 'Teste', 'tipo' => 'receita']);
        Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta 1', 'valor' => 100, 'data_vencimento' => now()->toDateString(), 'tipo' => 'receita']);

        $response = $this->actingAs($this->user)->getJson('/api/contas');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_can_filter_contas_a_vencer()
    {
        $categoria = CategoriaConta::create(['nome' => 'Teste', 'tipo' => 'receita']);
        $hoje = now()->toDateString();
        $amanha = now()->addDay()->toDateString();
        $ontem = now()->subDay()->toDateString();

        // A vencer
        $contaAVencer = Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta a Vencer', 'valor' => 100, 'data_vencimento' => $amanha, 'tipo' => 'receita', 'status' => 'pendente']);
        // Vencendo hoje
        Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta Hoje', 'valor' => 100, 'data_vencimento' => $hoje, 'tipo' => 'receita', 'status' => 'pendente']);

        $response = $this->actingAs($this->user)->getJson('/api/contas?situacao=a_vencer');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.id', $contaAVencer->id);
    }

    public function test_can_filter_contas_vencendo_hoje()
    {
        $categoria = CategoriaConta::create(['nome' => 'Teste', 'tipo' => 'receita']);
        $hoje = now()->toDateString();
        $amanha = now()->addDay()->toDateString();

        Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta a Vencer', 'valor' => 100, 'data_vencimento' => $amanha, 'tipo' => 'receita', 'status' => 'pendente']);
        $contaHoje = Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta Hoje', 'valor' => 100, 'data_vencimento' => $hoje, 'tipo' => 'receita', 'status' => 'pendente']);

        $response = $this->actingAs($this->user)->getJson('/api/contas?situacao=vencendo_hoje');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.id', $contaHoje->id);
    }

    public function test_can_filter_contas_atrasadas()
    {
        $categoria = CategoriaConta::create(['nome' => 'Teste', 'tipo' => 'receita']);
        $hoje = now()->toDateString();
        $ontem = now()->subDay()->toDateString();

        // Atrasada (pelo status)
        $contaAtrasada1 = Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta Atrasada 1', 'valor' => 100, 'data_vencimento' => $hoje, 'tipo' => 'receita', 'status' => 'vencido']);
        // Atrasada (pela data)
        $contaAtrasada2 = Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta Atrasada 2', 'valor' => 100, 'data_vencimento' => $ontem, 'tipo' => 'receita', 'status' => 'pendente']);

        // Nao atrasada
        Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta Hoje', 'valor' => 100, 'data_vencimento' => $hoje, 'tipo' => 'receita', 'status' => 'pendente']);

        $response = $this->actingAs($this->user)->getJson('/api/contas?situacao=atrasadas');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_contas_pagas()
    {
        $categoria = CategoriaConta::create(['nome' => 'Teste', 'tipo' => 'receita']);
        $hoje = now()->toDateString();

        Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta Pendente', 'valor' => 100, 'data_vencimento' => $hoje, 'tipo' => 'receita', 'status' => 'pendente']);
        $contaPaga = Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta Paga', 'valor' => 100, 'data_vencimento' => $hoje, 'tipo' => 'receita', 'status' => 'pago', 'data_pagamento' => $hoje]);

        $response = $this->actingAs($this->user)->getJson('/api/contas?situacao=pagas');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.id', $contaPaga->id);
    }

    public function test_can_create_conta()
    {
        $categoria = CategoriaConta::create(['nome' => 'Teste', 'tipo' => 'receita']);

        $data = [
            'id_categoria' => $categoria->id,
            'descricao' => 'Nova Conta',
            'valor' => 500.50,
            'data_vencimento' => now()->toDateString(),
            'tipo' => 'receita'
        ];

        $response = $this->actingAs($this->user)->postJson('/api/contas', $data);

        $response->assertStatus(201)
                 ->assertJsonPath('data.descricao', 'Nova Conta');

        $this->assertDatabaseHas('contas', ['descricao' => 'Nova Conta']);
    }

    public function test_can_update_conta()
    {
        $categoria = CategoriaConta::create(['nome' => 'Teste', 'tipo' => 'receita']);
        $conta = Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta Inicial', 'valor' => 100, 'data_vencimento' => now()->toDateString(), 'tipo' => 'receita']);

        $data = [
            'descricao' => 'Conta Atualizada',
            'status' => 'pago',
            'data_pagamento' => now()->toDateString()
        ];

        $response = $this->actingAs($this->user)->putJson("/api/contas/{$conta->id}", $data);

        $response->assertStatus(200)
                 ->assertJsonPath('data.descricao', 'Conta Atualizada')
                 ->assertJsonPath('data.status', 'pago');

        $this->assertDatabaseHas('contas', ['id' => $conta->id, 'descricao' => 'Conta Atualizada', 'status' => 'pago']);
    }

    public function test_can_delete_conta()
    {
        $categoria = CategoriaConta::create(['nome' => 'Teste', 'tipo' => 'receita']);
        $conta = Conta::create(['id_categoria' => $categoria->id, 'descricao' => 'Conta para Excluir', 'valor' => 100, 'data_vencimento' => now()->toDateString(), 'tipo' => 'receita']);

        $response = $this->actingAs($this->user)->deleteJson("/api/contas/{$conta->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('contas', ['id' => $conta->id]);
    }
}
