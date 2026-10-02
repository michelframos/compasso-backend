<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Professor;
use App\Models\Responsavel;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PessoasInstituicaoSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function defaultInstituicaoId(): int
    {
        return (int) Instituicao::where('slug', 'default')->value('id');
    }

    public function test_tabelas_pessoas_possuem_id_instituicao(): void
    {
        $tables = ['alunos', 'professores', 'responsaveis', 'medidas_alunos', 'responsaveis_alunos'];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'id_instituicao'), "Coluna id_instituicao ausente em {$table}");
        }
    }

    public function test_registros_existentes_recebem_instituicao_default(): void
    {
        $instituicaoId = $this->defaultInstituicaoId();

        $userAluno = User::factory()->create();
        $userProfessor = User::factory()->create();
        $userResponsavel = User::factory()->create();

        $aluno = Aluno::create(['id_usuario' => $userAluno->id]);
        $professor = Professor::create(['id_usuario' => $userProfessor->id]);
        $responsavel = Responsavel::create(['id_usuario' => $userResponsavel->id]);

        $this->assertSame($instituicaoId, (int) DB::table('alunos')->where('id', $aluno->id)->value('id_instituicao'));
        $this->assertSame($instituicaoId, (int) DB::table('professores')->where('id', $professor->id)->value('id_instituicao'));
        $this->assertSame($instituicaoId, (int) DB::table('responsaveis')->where('id', $responsavel->id)->value('id_instituicao'));
    }

    public function test_alunos_permitem_mesmo_usuario_em_instituicoes_diferentes(): void
    {
        $outraInstituicao = Instituicao::create([
            'slug' => 'escola-b',
            'nome_fantasia' => 'Escola B',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $user = User::factory()->create();

        Aluno::create(['id_usuario' => $user->id]);

        DB::table('alunos')->insert([
            'id_usuario' => $user->id,
            'id_instituicao' => $outraInstituicao->id,
            'criado_em' => now(),
        ]);

        $this->assertSame(2, DB::table('alunos')->where('id_usuario', $user->id)->count());
    }
}
