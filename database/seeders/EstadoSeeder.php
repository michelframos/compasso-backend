<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EstadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Buscando estados na API do IBGE...');

        try {
            $response = Http::get('https://servicodados.ibge.gov.br/api/v1/localidades/estados');

            if ($response->successful()) {
                $estados = $response->json();

                foreach ($estados as $estado) {
                    DB::table('estados')->updateOrInsert(
                        ['sigla' => $estado['sigla']],
                        [
                            'nome' => $estado['nome'],
                            'codigo_ibge' => $estado['id'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                $this->command->info('Estados sincronizados com sucesso.');
            } else {
                $this->command->error('Falha ao buscar estados na API do IBGE.');
            }
        } catch (\Exception $e) {
            $this->command->error('Erro ao acessar a API do IBGE: ' . $e->getMessage());
        }
    }
}
