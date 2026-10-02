<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoDataMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RemainingModulesInstituicaoSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_restantes_possuem_id_instituicao(): void
    {
        $tables = [
            'leads',
            'espetaculos',
            'apresentacoes',
            'apresentacoes_alunos',
            'instrumentos',
            'instrumento_historicos',
            'configuracoes_whatsapp',
            'configuracoes_notificacoes',
            'notificacoes_disparadas',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'id_instituicao'), "Coluna id_instituicao ausente em {$table}");
        }
    }

    public function test_configuracoes_empresa_foi_removida(): void
    {
        $this->assertFalse(Schema::hasTable('configuracoes_empresa'));
    }

    public function test_novos_registros_recebem_instituicao_default(): void
    {
        $instituicaoId = (int) Instituicao::where('slug', 'default')->value('id');

        $lead = Lead::create([
            'nome' => 'Lead Teste',
            'email' => 'lead@test.com',
            'telefone' => '11999999999',
            'status' => 'novo',
        ]);

        $this->assertSame($instituicaoId, (int) DB::table('leads')->where('id', $lead->id)->value('id_instituicao'));
    }
}
