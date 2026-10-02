<?php

namespace Tests\Feature;

use App\Modules\Core\Models\Depoimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDepoimentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_apenas_depoimentos_aprovados(): void
    {
        Depoimento::query()->create([
            'nome' => 'Pendente',
            'cargo' => 'Diretora',
            'escola' => 'Escola A',
            'conteudo' => 'Ainda não publicado.',
            'ordem' => 1,
            'aprovado' => false,
        ]);

        Depoimento::query()->create([
            'nome' => 'Carla Mendes',
            'cargo' => 'Diretora',
            'escola' => 'Escola Harmonia',
            'conteudo' => 'Depoimento publicado.',
            'ordem' => 2,
            'aprovado' => true,
            'aprovado_em' => now(),
        ]);

        $this->getJson('/api/public/depoimentos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Carla Mendes')
            ->assertJsonPath('data.0.cargo', 'Diretora')
            ->assertJsonPath('data.0.escola', 'Escola Harmonia')
            ->assertJsonMissingPath('data.0.aprovado')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'nome',
                    'cargo',
                    'escola',
                    'conteudo',
                    'avatar_url',
                ]],
            ]);
    }

    public function test_ordena_aprovados_por_ordem(): void
    {
        Depoimento::query()->create([
            'nome' => 'Segundo',
            'cargo' => 'Coord',
            'escola' => 'B',
            'conteudo' => 'Dois',
            'ordem' => 20,
            'aprovado' => true,
            'aprovado_em' => now(),
        ]);
        Depoimento::query()->create([
            'nome' => 'Primeiro',
            'cargo' => 'Coord',
            'escola' => 'A',
            'conteudo' => 'Um',
            'ordem' => 5,
            'aprovado' => true,
            'aprovado_em' => now(),
        ]);

        $this->getJson('/api/public/depoimentos')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Primeiro')
            ->assertJsonPath('data.1.nome', 'Segundo');
    }
}
