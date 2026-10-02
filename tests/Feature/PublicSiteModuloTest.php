<?php

namespace Tests\Feature;

use App\Modules\Core\Models\SiteModulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteModuloTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_apenas_modulos_aprovados(): void
    {
        SiteModulo::query()->create([
            'nome' => 'Pendente',
            'descricao' => 'Não deve aparecer',
            'recursos' => ['X'],
            'icone' => 'Users',
            'ordem' => 1,
            'aprovado' => false,
        ]);

        SiteModulo::query()->create([
            'nome' => 'Financeiro',
            'descricao' => 'Contas e PIX',
            'recursos' => ['PIX', 'Contratos'],
            'icone' => 'Wallet',
            'ordem' => 2,
            'aprovado' => true,
            'aprovado_em' => now(),
        ]);

        $this->getJson('/api/public/modulos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Financeiro')
            ->assertJsonPath('data.0.recursos.0', 'PIX')
            ->assertJsonMissingPath('data.0.aprovado');
    }

    public function test_ordena_aprovados_por_ordem(): void
    {
        SiteModulo::query()->create([
            'nome' => 'Segundo',
            'descricao' => 'B',
            'recursos' => [],
            'icone' => 'Star',
            'ordem' => 20,
            'aprovado' => true,
            'aprovado_em' => now(),
        ]);
        SiteModulo::query()->create([
            'nome' => 'Primeiro',
            'descricao' => 'A',
            'recursos' => [],
            'icone' => 'Users',
            'ordem' => 5,
            'aprovado' => true,
            'aprovado_em' => now(),
        ]);

        $this->getJson('/api/public/modulos')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Primeiro')
            ->assertJsonPath('data.1.nome', 'Segundo');
    }
}
