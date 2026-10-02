<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaProfessorMateriaisTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioProfessor;

    private User $usuarioAluno;

    private Turma $turma;

    private Turma $turmaAlheia;

    private MaterialTurma $materialAlheio;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $this->usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $usuarioOutroProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');

        $this->naInstituicao($this->instituicao, function () use ($usuarioOutroProfessor): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);

            $this->turma = $this->criarTurma($curso, $nivel, Professor::create(['id_usuario' => $this->usuarioProfessor->id]));
            $this->turmaAlheia = $this->criarTurma($curso, $nivel, Professor::create(['id_usuario' => $usuarioOutroProfessor->id]));

            Matricula::create([
                'id_aluno' => Aluno::create(['id_usuario' => $this->usuarioAluno->id])->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'ativa',
            ]);

            $this->materialAlheio = MaterialTurma::create([
                'id_turma' => $this->turmaAlheia->id,
                'titulo' => 'Material de outro professor',
                'link' => 'https://example.com/outro',
            ]);
        });
    }

    private function criarTurma(Curso $curso, Nivel $nivel, Professor $professor): Turma
    {
        return Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
            'valor_mensalidade' => 300,
        ]);
    }

    private function comoProfessor(): static
    {
        return $this->comoUsuario($this->usuarioProfessor, $this->instituicao);
    }

    public function test_professor_publica_arquivo_e_link_na_propria_turma(): void
    {
        $this->comoProfessor();

        $arquivo = $this->post('/api/materiais-turmas', [
            'id_turma' => $this->turma->id,
            'titulo' => 'Apostila',
            'descricao' => 'Primeiros acordes',
            'publico' => 'true',
            'file' => UploadedFile::fake()->create('apostila.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'arquivo')
            ->assertJsonPath('data.link', null)
            ->assertJsonPath('data.publico', true)
            ->json('data');

        Storage::disk('public')->assertExists($arquivo['file_path']);

        $this->postJson('/api/materiais-turmas', [
            'id_turma' => $this->turma->id,
            'titulo' => 'Vídeo de apoio',
            'link' => 'https://youtube.com/watch?v=abc',
            'publico' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'link')
            ->assertJsonPath('data.file_url', null)
            ->assertJsonPath('data.publico', false);

        $this->getJson("/api/turmas/{$this->turma->id}/materiais")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.titulo', 'Vídeo de apoio');
    }

    public function test_validacao_de_arquivo_e_link(): void
    {
        $this->comoProfessor();

        $this->postJson('/api/materiais-turmas', ['id_turma' => $this->turma->id, 'titulo' => 'Sem conteúdo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file', 'link']);

        $this->postJson('/api/materiais-turmas', [
            'id_turma' => $this->turma->id,
            'titulo' => 'Link inválido',
            'link' => 'javascript:alert(1)',
        ])->assertUnprocessable()->assertJsonValidationErrors('link');

        $this->post('/api/materiais-turmas', [
            'id_turma' => $this->turma->id,
            'titulo' => 'Script',
            'file' => UploadedFile::fake()->create('shell.php', 1, 'text/x-php'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_professor_edita_trocando_arquivo_por_link_e_exclui(): void
    {
        $this->comoProfessor();

        $material = $this->post('/api/materiais-turmas', [
            'id_turma' => $this->turma->id,
            'titulo' => 'Partitura',
            'file' => UploadedFile::fake()->create('partitura.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        $this->post("/api/materiais-turmas/{$material['id']}", [
            '_method' => 'PUT',
            'titulo' => 'Partitura (vídeo)',
            'link' => 'https://example.com/partitura',
            'publico' => '0',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Partitura (vídeo)')
            ->assertJsonPath('data.tipo', 'link')
            ->assertJsonPath('data.file_path', null)
            ->assertJsonPath('data.publico', false);

        Storage::disk('public')->assertMissing($material['file_path']);

        $this->putJson("/api/materiais-turmas/{$material['id']}", ['link' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('link');

        $this->deleteJson("/api/materiais-turmas/{$material['id']}")->assertNoContent();
        $this->assertDatabaseMissing('materiais_turmas', ['id' => $material['id']]);
    }

    public function test_professor_nao_gerencia_materiais_de_turma_alheia(): void
    {
        $this->comoProfessor();

        $this->postJson('/api/materiais-turmas', [
            'id_turma' => $this->turmaAlheia->id,
            'titulo' => 'Intruso',
            'link' => 'https://example.com',
        ])->assertForbidden();

        $this->putJson("/api/materiais-turmas/{$this->materialAlheio->id}", ['titulo' => 'Alterado'])->assertNotFound();
        $this->deleteJson("/api/materiais-turmas/{$this->materialAlheio->id}")->assertForbidden();
        $this->assertDatabaseHas('materiais_turmas', ['id' => $this->materialAlheio->id, 'titulo' => 'Material de outro professor']);
    }

    public function test_aluno_ve_somente_materiais_publicos_e_nao_gerencia(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            MaterialTurma::create(['id_turma' => $this->turma->id, 'titulo' => 'Público', 'link' => 'https://example.com/a', 'publico' => true]);
            MaterialTurma::create(['id_turma' => $this->turma->id, 'titulo' => 'Rascunho', 'link' => 'https://example.com/b', 'publico' => false]);
        });

        $this->comoUsuario($this->usuarioAluno, $this->instituicao);

        $this->assertSame(
            ['Público'],
            $this->getJson("/api/turmas/{$this->turma->id}/materiais")->assertOk()->json('data.*.titulo')
        );

        $this->postJson('/api/materiais-turmas', [
            'id_turma' => $this->turma->id,
            'titulo' => 'Aluno publicando',
            'link' => 'https://example.com',
        ])->assertForbidden();
    }

    public function test_isolamento_entre_instituicoes(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'professor');

        $materialDeFora = $this->naInstituicao($outra, function () use ($usuarioOutro): MaterialTurma {
            $turma = $this->criarTurma(
                Curso::create(['nome' => 'Bateria', 'descricao' => 'Percussão']),
                Nivel::create(['nome' => 'Avançado']),
                Professor::create(['id_usuario' => $usuarioOutro->id])
            );

            return MaterialTurma::create(['id_turma' => $turma->id, 'titulo' => 'De fora', 'link' => 'https://example.com/fora']);
        });

        $this->comoProfessor();

        $this->postJson('/api/materiais-turmas', [
            'id_turma' => $materialDeFora->id_turma,
            'titulo' => 'Cruzando escolas',
            'link' => 'https://example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors('id_turma');
        $this->putJson("/api/materiais-turmas/{$materialDeFora->id}", ['titulo' => 'X'])->assertNotFound();
        $this->deleteJson("/api/materiais-turmas/{$materialDeFora->id}")->assertNotFound();
    }
}
