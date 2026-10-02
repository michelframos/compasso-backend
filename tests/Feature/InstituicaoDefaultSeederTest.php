<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoDataMigrator;
use Database\Seeders\InstituicaoDefaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstituicaoDefaultSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_instituicao_default_e_vincula_usuarios(): void
    {
        DB::table('instituicoes_usuarios')->delete();
        Instituicao::query()->forceDelete();

        User::factory()->create(['role' => 'admin', 'email' => 'admin@test.com']);
        User::factory()->create(['role' => 'professor', 'email' => 'prof@test.com']);

        $result = (new InstituicaoDataMigrator)->migrate();

        $instituicao = Instituicao::where('slug', 'default')->first();

        $this->assertNotNull($instituicao);
        $this->assertSame($result['instituicao_id'], $instituicao->id);
        $this->assertSame(Instituicao::STATUS_ATIVO, $instituicao->status);
        $this->assertSame(2, InstituicaoUsuario::count());
        $this->assertDatabaseHas('instituicoes_usuarios', [
            'id_instituicao' => $instituicao->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(InstituicaoDefaultSeeder::class);
        $this->seed(InstituicaoDefaultSeeder::class);

        $this->assertSame(1, Instituicao::count());
    }

    public function test_migrations_criam_tabelas_de_instituicao(): void
    {
        $this->assertTrue(Schema::hasTable('instituicoes'));
        $this->assertTrue(Schema::hasTable('instituicoes_usuarios'));
        $this->assertTrue(Schema::hasColumn('personal_access_tokens', 'id_instituicao'));
    }

    public function test_comando_migrate_data_sincroniza_usuarios(): void
    {
        User::factory()->count(2)->create();

        $this->artisan('instituicoes:migrate-data')
            ->assertSuccessful();

        $this->assertSame(2, InstituicaoUsuario::count());
    }
}
