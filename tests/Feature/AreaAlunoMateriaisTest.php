<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoMateriaisTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $bruno;

    private Aluno $carla;

    private Turma $turmaAna;

    private Turma $turmaBruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $usuarioCarla = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Carla Lima']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno, $usuarioCarla, $usuarioPaula): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $paula = Professor::create(['id_usuario' => $usuarioPaula->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $this->carla = Aluno::create(['id_usuario' => $usuarioCarla->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));
            $this->carla->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $this->turmaAna = $this->turma($curso, $nivel, $paula, 'Turma Ana');
            $turmaAntiga = $this->turma($curso, $nivel, $paula, 'Turma antiga');
            $this->turmaBruno = $this->turma($curso, $nivel, $paula, 'Turma Bruno');
            $turmaCarla = $this->turma($curso, $nivel, $paula, 'Turma Carla');

            $this->matricular($this->ana, $this->turmaAna, 'ativa');
            $this->matricular($this->ana, $turmaAntiga, 'cancelada');
            $this->matricular($this->bruno, $this->turmaBruno, 'ativa');
            $this->matricular($this->carla, $turmaCarla, 'ativa');

            MaterialTurma::create([
                'id_turma' => $this->turmaAna->id,
                'titulo' => 'Partitura da Ana',
                'descricao' => 'Primeiras lições',
                'link' => 'https://exemplo.test/ana',
                'publico' => true,
            ]);
            MaterialTurma::create([
                'id_turma' => $this->turmaAna->id,
                'titulo' => 'Anotação interna',
                'descricao' => 'segredo da secretaria',
                'link' => 'https://exemplo.test/interno',
                'publico' => false,
            ]);
            MaterialTurma::create([
                'id_turma' => $this->turmaAna->id,
                'titulo' => 'Áudio da Ana',
                'file_path' => 'materiais_turmas/ana/audio.mp3',
                'file_type' => 'audio/mpeg',
                'publico' => true,
            ]);
            MaterialTurma::create([
                'id_turma' => $this->turmaBruno->id,
                'titulo' => 'Partitura do Bruno',
                'link' => 'https://exemplo.test/bruno',
                'publico' => true,
            ]);
            MaterialTurma::create([
                'id_turma' => $turmaAntiga->id,
                'titulo' => 'Apostila antiga',
                'link' => 'https://exemplo.test/antiga',
                'publico' => true,
            ]);
            MaterialTurma::create([
                'id_turma' => $turmaCarla->id,
                'titulo' => 'Partitura da Carla',
                'link' => 'https://exemplo.test/carla',
                'publico' => true,
            ]);
        });
    }

    public function test_aluno_ve_so_os_materiais_publicos_das_proprias_turmas(): void
    {
        $resposta = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/materiais')
            ->assertOk();

        $titulos = collect($resposta->json('data'))->pluck('titulo')->all();

        $this->assertSame(['Apostila antiga', 'Áudio da Ana', 'Partitura da Ana'], $titulos);
        $this->assertSame('link', $resposta->json('data.2.tipo'));
        $this->assertSame('https://exemplo.test/ana', $resposta->json('data.2.link'));
        $this->assertSame('Primeiras lições', $resposta->json('data.2.descricao'));
        $this->assertSame('Turma Ana', $resposta->json('data.2.turma.nome'));
        $this->assertSame('Violão', $resposta->json('data.2.turma.curso'));
        $this->assertSame('arquivo', $resposta->json('data.1.tipo'));
        $this->assertStringContainsString('materiais_turmas/ana/audio.mp3', $resposta->json('data.1.file_url'));
        $this->assertArrayNotHasKey('file_path', $resposta->json('data.1'));
        $this->assertArrayNotHasKey('publico', $resposta->json('data.1'));

        $corpo = $resposta->getContent();
        $this->assertStringNotContainsString('Anotação interna', $corpo);
        $this->assertStringNotContainsString('segredo da secretaria', $corpo);
        $this->assertStringNotContainsString('Partitura do Bruno', $corpo);
        $this->assertStringNotContainsString('Partitura da Carla', $corpo);

        $filtrados = $this->getJson('/api/aluno/me/materiais?id_turma='.$this->turmaAna->id)
            ->assertOk()
            ->json('data');

        $this->assertSame(['Áudio da Ana', 'Partitura da Ana'], collect($filtrados)->pluck('titulo')->all());

        $this->getJson('/api/aluno/me/materiais?id_turma='.$this->turmaBruno->id)->assertForbidden();
        $this->getJson('/api/aluno/me/materiais?id_turma=99999')->assertNotFound();
        $this->getJson('/api/aluno/me/materiais?id_turma=abc')->assertStatus(422);
    }

    public function test_responsavel_consulta_um_dependente_por_vez_e_os_demais_papeis_nao(): void
    {
        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/materiais')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $daAna = $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/materiais?id_aluno='.$this->ana->id)
            ->assertOk()
            ->json('data');

        $this->assertSame(['Apostila antiga', 'Áudio da Ana', 'Partitura da Ana'], collect($daAna)->pluck('titulo')->all());

        $daCarla = $this->getJson('/api/aluno/me/materiais?id_aluno='.$this->carla->id)
            ->assertOk()
            ->json('data');

        $this->assertSame(['Partitura da Carla'], collect($daCarla)->pluck('titulo')->all());

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/materiais?id_aluno='.$this->bruno->id)
            ->assertForbidden();

        $this->getJson('/api/aluno/me/materiais?id_aluno='.$this->ana->id.'&id_turma='.$this->turmaBruno->id)
            ->assertForbidden();

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/materiais')
                ->assertForbidden();
        }
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/materiais')
            ->assertUnauthorized();
    }

    public function test_aluno_de_outra_instituicao_recebe_403(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'aluno', ['nome' => 'Aluno de Fora']);
        $this->naInstituicao($outra, fn () => Aluno::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/aluno/me/materiais')
            ->assertForbidden();
    }

    private function turma(Curso $curso, Nivel $nivel, Professor $professor, string $descricao): Turma
    {
        return Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'descricao' => $descricao,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::EM_ANDAMENTO,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
            'valor_mensalidade' => 350,
        ]);
    }

    private function matricular(Aluno $aluno, Turma $turma, string $status): void
    {
        Matricula::create([
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
            'tipo' => 'turma',
            'data' => '2026-09-01',
            'status' => $status,
        ]);
    }
}
