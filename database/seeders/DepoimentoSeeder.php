<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Depoimento;
use Illuminate\Database\Seeder;

class DepoimentoSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'nome' => 'Carla Mendes',
                'cargo' => 'Diretora',
                'escola' => 'Escola Harmonia',
                'conteudo' => 'Antes do Compasso eu controlava tudo em planilhas. Hoje tenho visão completa do financeiro e da ocupação das salas em segundos.',
                'ordem' => 1,
            ],
            [
                'nome' => 'Ricardo Souza',
                'cargo' => 'Proprietário',
                'escola' => 'Instituto Musical RS',
                'conteudo' => 'A gestão de matrículas ficou muito mais ágil. Os pais preenchem online e eu só aprovo. Reduzi o tempo administrativo pela metade.',
                'ordem' => 2,
            ],
            [
                'nome' => 'Fernanda Lima',
                'cargo' => 'Coordenadora',
                'escola' => 'Studio Compasso Musical',
                'conteudo' => 'O dashboard de inadimplência me ajuda a agir rápido. Desde que comecei a usar, a taxa de inadimplência caiu 30%.',
                'ordem' => 3,
            ],
        ];

        foreach ($items as $item) {
            Depoimento::query()->updateOrCreate(
                ['nome' => $item['nome'], 'escola' => $item['escola']],
                [
                    ...$item,
                    'aprovado' => true,
                    'aprovado_em' => now(),
                ],
            );
        }
    }
}
