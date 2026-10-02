<?php

namespace App\Console\Commands;

use App\Modules\Core\Support\InstituicaoDataMigrator;
use Illuminate\Console\Command;

class InstituicoesMigrateData extends Command
{
    protected $signature = 'instituicoes:migrate-data';

    protected $description = 'Migra dados legados (configuracoes_empresa e usuarios.role) para instituicoes e pivot';

    public function handle(InstituicaoDataMigrator $migrator): int
    {
        $this->info('Iniciando migração de dados multi-tenant...');

        $result = $migrator->migrate();

        $this->info("Instituição default ID: {$result['instituicao_id']}");
        $this->info("Usuários sincronizados no pivot: {$result['usuarios_sincronizados']}");
        $this->info('Dados de configuracoes_empresa migrados: '.($result['dados_empresa_migrados'] ? 'sim' : 'não'));
        $this->comment('A coluna usuarios.role permanece por compatibilidade; fonte de verdade: instituicoes_usuarios.role');

        return Command::SUCCESS;
    }
}
