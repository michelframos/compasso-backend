<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CidadeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Buscando cidades na API do IBGE...');

        try {
            $response = Http::get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios');

            if ($response->successful()) {
                $cidades = $response->json();
                $count = count($cidades);
                $this->command->info("Processando {$count} cidades...");

                // Mapeia códigos IBGE dos estados para IDs do banco de dados
                $estadosMap = DB::table('estados')->pluck('id', 'codigo_ibge');

                $bar = $this->command->getOutput()->createProgressBar($count);
                $bar->start();

                $chunkSize = 500;
                $data = [];

                foreach ($cidades as $index => $cidade) {
                    $codigoIbgeEstado = $cidade['microrregiao']['mesorregiao']['UF']['id'];
                    
                    if (isset($estadosMap[$codigoIbgeEstado])) {
                        // Usando updateOrInsert para garantir que não hajam duplicados e permitir atualizações
                        DB::table('cidades')->updateOrInsert(
                            ['codigo_ibge' => $cidade['id']],
                            [
                                'nome' => $cidade['nome'],
                                'id_estado' => $estadosMap[$codigoIbgeEstado],
                                'updated_at' => now(),
                                'created_at' => now(), // created_at será ignorado no update
                            ]
                        );
                    }
                    
                    $bar->advance();
                }

                $bar->finish();
                $this->command->newLine();
                $this->command->info('Cidades sincronizadas com sucesso.');
            } else {
                $this->command->error('Falha ao buscar cidades na API do IBGE.');
            }
        } catch (\Exception $e) {
            $this->command->error('Erro ao acessar a API do IBGE: ' . $e->getMessage());
        }
    }
}
