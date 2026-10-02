<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Models\PlatformImpersonationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_planos_assinatura_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('planos_assinatura'));
        $this->assertTrue(Schema::hasColumns('planos_assinatura', [
            'id',
            'slug',
            'nome',
            'descricao',
            'preco_mensal',
            'limite_alunos',
            'ativo',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
    }

    public function test_instituicoes_has_assinatura_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('instituicoes', [
            'id_plano_assinatura',
            'trial_ends_at',
            'trial_usa_padrao',
            'assinatura_inicia_em',
            'assinatura_status',
        ]));
    }

    public function test_platform_impersonation_logs_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('platform_impersonation_logs'));
        $this->assertTrue(Schema::hasColumns('platform_impersonation_logs', [
            'id_super_admin',
            'id_usuario_alvo',
            'id_instituicao',
            'token_id',
            'ip',
            'user_agent',
            'iniciado_em',
            'encerrado_em',
        ]));
    }

    public function test_personal_access_tokens_has_impersonator_user_id(): void
    {
        $this->assertTrue(Schema::hasColumn('personal_access_tokens', 'impersonator_user_id'));
    }

    public function test_default_instituicao_receives_trial_on_migration(): void
    {
        $instituicao = Instituicao::where('slug', 'default')->first();

        $this->assertNotNull($instituicao);
        $this->assertNotNull($instituicao->trial_ends_at);
        $this->assertTrue($instituicao->trial_usa_padrao);
        $this->assertSame(Instituicao::ASSINATURA_TRIALING, $instituicao->assinatura_status);
    }

    public function test_pode_criar_plano_assinatura_e_vincular_instituicao(): void
    {
        $plano = PlanoAssinatura::create([
            'slug' => 'basico',
            'nome' => 'Básico',
            'descricao' => 'Plano inicial',
            'preco_mensal' => 99.90,
            'limite_alunos' => 100,
            'ativo' => true,
        ]);

        $instituicao = Instituicao::create([
            'slug' => 'escola-teste',
            'nome_fantasia' => 'Escola Teste',
            'status' => Instituicao::STATUS_ATIVO,
            'id_plano_assinatura' => $plano->id,
            'trial_ends_at' => now()->addDays(30),
            'trial_usa_padrao' => false,
            'assinatura_status' => Instituicao::ASSINATURA_TRIALING,
        ]);

        $instituicao->load('planoAssinatura');

        $this->assertSame('basico', $instituicao->planoAssinatura->slug);
        $this->assertFalse($instituicao->trial_usa_padrao);
    }

    public function test_platform_config_default_trial_days(): void
    {
        $this->assertSame(14, config('platform.default_trial_days'));
    }

    public function test_platform_impersonation_log_model_persiste(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $alvo = User::factory()->create();
        $instituicao = Instituicao::where('slug', 'default')->first();

        $log = PlatformImpersonationLog::create([
            'id_super_admin' => $superAdmin->id,
            'id_usuario_alvo' => $alvo->id,
            'id_instituicao' => $instituicao->id,
            'iniciado_em' => now(),
        ]);

        $this->assertTrue($log->isAtivo());
        $this->assertDatabaseHas('platform_impersonation_logs', [
            'id' => $log->id,
            'id_instituicao' => $instituicao->id,
            'encerrado_em' => null,
        ]);
    }
}
