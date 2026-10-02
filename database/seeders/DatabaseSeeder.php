<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Ordem:
     * 1. Geo (estados/cidades) — pulados se já existirem
     * 2. Platform (planos, site, depoimentos, super admin)
     * 3. Escolas demo + dados de domínio
     *
     * Credenciais: ver saída do DemoTenantSeeder (senha: password).
     */
    public function run(): void
    {
        if (\App\Modules\Core\Models\Estado::query()->doesntExist()) {
            $this->call(EstadoSeeder::class);
        } else {
            $this->command?->info('Estados já existem — pulando EstadoSeeder.');
        }

        if (\App\Modules\Core\Models\Cidade::query()->doesntExist()) {
            $this->call(CidadeSeeder::class);
        } else {
            $this->command?->info('Cidades já existem — pulando CidadeSeeder.');
        }

        $this->call([
            PlanoAssinaturaSeeder::class,
            ModuloSiteSeeder::class,
            DepoimentoSeeder::class,
            PlatformAdminSeeder::class,
            DemoTenantSeeder::class,
        ]);
    }
}
