<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Conta;
use App\Models\CategoriaConta;
use App\Models\User;
use Carbon\Carbon;

class ContaRepeticaoTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_create_multiple_contas_with_monthly_repetition(): void
    {
        $categoria = CategoriaConta::create(['nome' => 'Mensalidade', 'tipo' => 'receita']);

        $data = [
            'id_categoria' => $categoria->id,
            'descricao' => 'Mensalidade Teste',
            'valor' => 100.00,
            'data_vencimento' => '2026-03-10',
            'tipo' => 'receita',
            'repeticao' => 'mensal',
            'quantidade_repeticoes' => 3
        ];

        $response = $this->actingAs($this->user)->postJson('/api/contas', $data);

        $response->assertStatus(200); // Retorna ResourceCollection, que é status 200 por padrão do Resource::collection

        $this->assertCount(3, Conta::all());

        $this->assertDatabaseHas('contas', [
            'data_vencimento' => '2026-03-10',
            'numero_parcela' => 1,
            'mes_referencia' => 3,
            'ano_referencia' => 2026
        ]);

        $this->assertDatabaseHas('contas', [
            'data_vencimento' => '2026-04-10',
            'numero_parcela' => 2,
            'mes_referencia' => 4,
            'ano_referencia' => 2026
        ]);

        $this->assertDatabaseHas('contas', [
            'data_vencimento' => '2026-05-10',
            'numero_parcela' => 3,
            'mes_referencia' => 5,
            'ano_referencia' => 2026
        ]);
    }

    public function test_can_create_multiple_contas_with_weekly_repetition(): void
    {
        $categoria = CategoriaConta::create(['nome' => 'Serviço', 'tipo' => 'despesa']);

        $data = [
            'id_categoria' => $categoria->id,
            'descricao' => 'Limpeza Semanal',
            'valor' => 50.00,
            'data_vencimento' => '2026-03-02',
            'tipo' => 'despesa',
            'repeticao' => 'semanal',
            'quantidade_repeticoes' => 2
        ];

        $response = $this->actingAs($this->user)->postJson('/api/contas', $data);

        $response->assertStatus(200);

        $this->assertCount(2, Conta::all());

        $this->assertDatabaseHas('contas', [
            'data_vencimento' => '2026-03-02',
            'numero_parcela' => 1
        ]);

        $this->assertDatabaseHas('contas', [
            'data_vencimento' => '2026-03-09',
            'numero_parcela' => 2
        ]);
    }

    public function test_can_create_multiple_contas_with_daily_repetition(): void
    {
        $categoria = CategoriaConta::create(['nome' => 'Diaria', 'tipo' => 'despesa']);

        $data = [
            'id_categoria' => $categoria->id,
            'descricao' => 'Gasto Diario',
            'valor' => 10.00,
            'data_vencimento' => '2026-03-01',
            'tipo' => 'despesa',
            'repeticao' => 'diaria',
            'quantidade_repeticoes' => 5
        ];

        $response = $this->actingAs($this->user)->postJson('/api/contas', $data);

        $response->assertStatus(200);

        $this->assertCount(5, Conta::all());

        for ($i = 0; $i < 5; $i++) {
            $date = Carbon::parse('2026-03-01')->addDays($i)->toDateString();
            $this->assertDatabaseHas('contas', [
                'data_vencimento' => $date,
                'numero_parcela' => $i + 1
            ]);
        }
    }
}
