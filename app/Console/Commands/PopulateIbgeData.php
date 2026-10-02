<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\CidadeSeeder;

class PopulateIbgeData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:populate-ibge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Popula as tabelas de estados e cidades utilizando a API do IBGE';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando população de dados via API do IBGE...');

        $this->call('db:seed', ['--class' => 'EstadoSeeder']);
        $this->call('db:seed', ['--class' => 'CidadeSeeder']);

        $this->info('Processo concluído com sucesso.');
    }
}
