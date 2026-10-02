<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\ContaPagamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ContaPagamentoTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_list_pagamentos_of_a_conta()
    {
        $conta = Conta::factory()->create(['valor' => 100, 'status' => 'pendente']);

        $conta->pagamentos()->create([
            'valor_pago' => 50,
            'data_pagamento' => now(),
            'forma_pagamento' => 'PIX',
            'observacoes' => 'Metade'
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/contas/{$conta->id}/pagamentos");

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    public function test_can_register_partial_payment()
    {
        $conta = Conta::factory()->create(['valor' => 200, 'status' => 'pendente']);

        $payload = [
            'valor_pago' => 50,
            'data_pagamento' => now()->toDateString(),
            'forma_pagamento' => 'Dinheiro',
            'observacoes' => 'Teste'
        ];

        $response = $this->actingAs($this->user)->postJson("/api/contas/{$conta->id}/pagamentos", $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('conta_pagamentos', [
            'id_conta' => $conta->id,
            'valor_pago' => 50
        ]);

        $this->assertDatabaseHas('contas', [
            'id' => $conta->id,
            'status' => 'pago_parcialmente'
        ]);
    }

    public function test_changes_status_to_pago_when_fully_paid()
    {
        $conta = Conta::factory()->create(['valor' => 150, 'status' => 'pendente']);

        $payload = [
            'valor_pago' => 150,
            'data_pagamento' => now()->toDateString(),
            'forma_pagamento' => 'Cartão',
        ];

        $response = $this->actingAs($this->user)->postJson("/api/contas/{$conta->id}/pagamentos", $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('contas', [
            'id' => $conta->id,
            'status' => 'pago'
        ]);
    }

    public function test_cannot_pay_more_than_owed_balance()
    {
        $conta = Conta::factory()->create(['valor' => 100, 'status' => 'pendente']);

        $payload = [
            'valor_pago' => 101, // 1 real a mais
            'data_pagamento' => now()->toDateString(),
            'forma_pagamento' => 'PIX',
        ];

        $response = $this->actingAs($this->user)->postJson("/api/contas/{$conta->id}/pagamentos", $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['valor_pago']);
    }

    public function test_can_delete_payment_and_status_reverts()
    {
        $conta = Conta::factory()->create(['valor' => 100, 'status' => 'pago_parcialmente']);
        $pagamento1 = $conta->pagamentos()->create(['valor_pago' => 50, 'data_pagamento' => now(), 'forma_pagamento' => 'PIX']);
        $pagamento2 = $conta->pagamentos()->create(['valor_pago' => 50, 'data_pagamento' => now(), 'forma_pagamento' => 'Dinheiro']);

        // Simular que com 100 a conta virou "pago"
        $conta->update(['status' => 'pago']);

        $response = $this->actingAs($this->user)->deleteJson("/api/pagamentos/{$pagamento2->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('conta_pagamentos', ['id' => $pagamento2->id]);

        $this->assertDatabaseHas('contas', [
            'id' => $conta->id,
            'status' => 'pago_parcialmente'
        ]);
    }
}
