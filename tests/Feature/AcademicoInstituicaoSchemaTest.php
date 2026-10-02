<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Matricula;
use App\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicoInstituicaoSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function defaultInstituicaoId(): int
    {
        return (int) Instituicao::where('slug', 'default')->value('id');
    }

    public function test_tabelas_academico_possuem_id_instituicao(): void
    {
        $tables = [
            'cursos',
            'niveis',
            'turmas',
            'turma_horarios',
            'matriculas',
            'matricula_historico',
            'aulas_turmas',
            'aulas_presencas',
            'materiais_turmas',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'id_instituicao'), "Coluna id_instituicao ausente em {$table}");
        }
    }

    public function test_novos_registros_recebem_instituicao_default(): void
    {
        $instituicaoId = $this->defaultInstituicaoId();

        $turma = Turma::factory()->create();
        $aluno = Aluno::factory()->create();
        $matricula = Matricula::factory()->create([
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
        ]);

        $this->assertSame($instituicaoId, (int) DB::table('turmas')->where('id', $turma->id)->value('id_instituicao'));
        $this->assertSame($instituicaoId, (int) DB::table('matriculas')->where('id', $matricula->id)->value('id_instituicao'));
    }

    public function test_matriculas_permitem_mesmo_aluno_turma_em_instituicoes_diferentes(): void
    {
        $outraInstituicao = Instituicao::create([
            'slug' => 'escola-academico',
            'nome_fantasia' => 'Escola Acadêmico',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $turma = Turma::factory()->create();
        $aluno = Aluno::factory()->create();

        Matricula::factory()->create([
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
        ]);

        DB::table('matriculas')->insert([
            'id_instituicao' => $outraInstituicao->id,
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
            'data' => now()->toDateString(),
            'status' => 'ativa',
        ]);

        $this->assertSame(2, DB::table('matriculas')
            ->where('id_aluno', $aluno->id)
            ->where('id_turma', $turma->id)
            ->count());
    }
}
