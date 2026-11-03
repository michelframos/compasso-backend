<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\TemplateCertificado;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Support\LayoutCertificado;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class TemplateCertificadoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $secretaria;

    private User $usuarioAna;

    private Aluno $ana;

    private Matricula $matriculaAna;

    private Curso $curso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00');
        Storage::fake('public');

        $this->instituicao = $this->instituicaoDefault();
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria', ['nome' => 'Secretaria']);
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioPaula): void {
            $this->curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $paula = Professor::create(['id_usuario' => $usuarioPaula->id]);
            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);

            $turma = Turma::create([
                'id_curso' => $this->curso->id,
                'id_nivel' => $nivel->id,
                'id_professor' => $paula->id,
                'descricao' => 'Turma Ana',
                'maximo_alunos' => 10,
                'status' => TurmaStatus::EM_ANDAMENTO,
                'tipo_agendamento' => 'quantidade',
                'quantidade_aulas' => 10,
                'data_inicio' => '2026-09-01',
                'valor_mensalidade' => 350,
            ]);

            $this->matriculaAna = Matricula::create([
                'id_aluno' => $this->ana->id,
                'id_turma' => $turma->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'completada',
            ]);

            foreach (['2026-09-08', '2026-09-15', '2026-09-22', '2026-09-29'] as $i => $data) {
                $aula = AulaTurma::create([
                    'id_turma' => $turma->id,
                    'id_curso' => $turma->id_curso,
                    'id_professor' => $paula->id,
                    'data' => $data,
                    'hora_inicio' => '14:00',
                    'hora_termino' => '15:00',
                    'status' => 'concluida',
                    'tipo' => 'regular',
                ]);
                AulaPresenca::create([
                    'id_aula_turma' => $aula->id,
                    'id_aluno' => $this->ana->id,
                    'status' => $i === 3 ? 'ausente' : 'presente',
                ]);
            }
        });
    }

    public function test_crud_de_template_com_fundo_e_layout_json(): void
    {
        $layout = [
            'version' => 1,
            'elementos' => [[
                'id' => 'el_1',
                'tipo' => 'texto',
                'x' => 10,
                'y' => 35,
                'w' => 80,
                'h' => 12,
                'texto' => 'Certificamos que {{nome_aluno}}',
                'fonteTamanho' => 28,
                'fontePeso' => 'bold',
                'alinhamento' => 'center',
                'cor' => '#18181b',
            ]],
        ];

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/templates-certificados', [
                'nome' => 'Certificado Violão',
                'conteudo' => json_encode($layout),
            ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Certificado Violão')
            ->assertJsonPath('data.layout.version', 1)
            ->assertJsonPath('data.layout.elementos.0.texto', 'Certificamos que {{nome_aluno}}');

        $id = TemplateCertificado::query()->value('id');
        $salvo = TemplateCertificado::findOrFail($id);
        $this->assertTrue(LayoutCertificado::ehLayoutJson($salvo->conteudo));

        $this->putJson('/api/templates-certificados/'.$id, [
            'nome' => 'Certificado Violão 2026',
            'conteudo' => json_encode([
                'version' => 1,
                'elementos' => [[
                    'id' => 'el_1',
                    'tipo' => 'texto',
                    'x' => 10,
                    'y' => 40,
                    'w' => 80,
                    'h' => 10,
                    'texto' => '{{nome_aluno}} — {{carga_horaria}}h',
                    'fonteTamanho' => 20,
                    'fontePeso' => 'normal',
                    'alinhamento' => 'center',
                    'cor' => '#18181b',
                ]],
            ]),
        ])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Certificado Violão 2026');

        $arquivo = UploadedFile::fake()->image('fundo.jpg', 1200, 800);
        $this->post('/api/templates-certificados/'.$id.'/fundo', ['fundo' => $arquivo], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $template = TemplateCertificado::findOrFail($id);
        $this->assertNotEmpty($template->fundo_path);
        Storage::disk('public')->assertExists($template->fundo_path);

        $elemento = UploadedFile::fake()->image('selo.png', 200, 200);
        $this->post('/api/templates-certificados/'.$id.'/elementos', ['imagem' => $elemento], [
            'Accept' => 'application/json',
        ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['src', 'url']]);

        $src = $this->post('/api/templates-certificados/'.$id.'/elementos', [
            'imagem' => UploadedFile::fake()->image('logo.png', 100, 100),
        ], ['Accept' => 'application/json'])->json('data.src');
        Storage::disk('public')->assertExists($src);

        $this->deleteJson('/api/templates-certificados/'.$id.'/fundo')
            ->assertOk()
            ->assertJsonPath('data.fundo_path', null);

        $this->deleteJson('/api/templates-certificados/'.$id)->assertNoContent();
        $this->assertSoftDeleted('templates_certificados', ['id' => $id]);
    }

    public function test_migracao_lazy_de_html_legado_no_resource(): void
    {
        $template = $this->naInstituicao($this->instituicao, fn () => TemplateCertificado::create([
            'nome' => 'Legado',
            'conteudo' => '<p>Aluno {{nome_aluno}}</p>',
        ]));

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->getJson('/api/templates-certificados/'.$template->id)
            ->assertOk()
            ->assertJsonPath('data.layout.version', 1)
            ->assertJsonPath('data.layout.elementos.0.tipo', 'texto')
            ->assertJsonPath('data.layout.elementos.0.texto', '<p>Aluno {{nome_aluno}}</p>');

        $this->assertFalse(LayoutCertificado::ehLayoutJson($template->fresh()->conteudo));
    }

    public function test_emissao_com_layout_posicionado_grava_snapshot_e_pdf(): void
    {
        $layout = LayoutCertificado::encode([
            'version' => 1,
            'elementos' => [[
                'id' => 'el_1',
                'tipo' => 'texto',
                'x' => 10,
                'y' => 35,
                'w' => 80,
                'h' => 12,
                'texto' => 'Aluno {{nome_aluno}} no curso {{nome_curso}} com {{carga_horaria}} horas. Código {{codigo_verificacao}}',
                'fonteTamanho' => 18,
                'fontePeso' => 'bold',
                'alinhamento' => 'center',
                'cor' => '#18181b',
            ]],
        ]);

        $template = $this->naInstituicao($this->instituicao, fn () => TemplateCertificado::create([
            'nome' => 'Modelo Ana',
            'conteudo' => $layout,
            'fundo_path' => null,
        ]));

        $this->curso->update(['id_template_certificado' => $template->id]);

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/matriculas/'.$this->matriculaAna->id.'/certificados')
            ->assertCreated()
            ->assertJsonPath('data.aluno.nome', 'Ana Lima');

        $certificado = Certificado::query()->where('id_matricula', $this->matriculaAna->id)->firstOrFail();
        $this->assertNotEmpty($certificado->conteudo_gerado);
        $this->assertStringContainsString('Ana Lima', $certificado->conteudo_gerado);
        $this->assertStringContainsString('Violão', $certificado->conteudo_gerado);
        $this->assertStringContainsString($certificado->codigo_verificacao, $certificado->conteudo_gerado);
        $this->assertStringContainsString('position:absolute', $certificado->conteudo_gerado);

        $pdf = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->get('/api/aluno/me/certificados/'.$certificado->id.'/pdf');
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_emissao_com_html_legado_ainda_funciona(): void
    {
        $template = $this->naInstituicao($this->instituicao, fn () => TemplateCertificado::create([
            'nome' => 'HTML antigo',
            'conteudo' => '<p>Aluno {{nome_aluno}} no curso {{nome_curso}}</p>',
        ]));

        $this->curso->update(['id_template_certificado' => $template->id]);

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/matriculas/'.$this->matriculaAna->id.'/certificados')
            ->assertCreated();

        $certificado = Certificado::query()->where('id_matricula', $this->matriculaAna->id)->firstOrFail();
        $this->assertStringContainsString('Ana Lima', $certificado->conteudo_gerado);
        $this->assertStringContainsString('Violão', $certificado->conteudo_gerado);
    }

    public function test_emissao_sem_template_continua_no_layout_legado(): void
    {
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/matriculas/'.$this->matriculaAna->id.'/certificados')
            ->assertCreated();

        $certificado = Certificado::query()->where('id_matricula', $this->matriculaAna->id)->firstOrFail();
        $this->assertNull($certificado->conteudo_gerado);

        $pdf = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->get('/api/aluno/me/certificados/'.$certificado->id.'/pdf');
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_curso_pode_associar_e_desassociar_template(): void
    {
        $template = $this->naInstituicao($this->instituicao, fn () => TemplateCertificado::create([
            'nome' => 'Padrão',
            'conteudo' => LayoutCertificado::encode([
                'version' => 1,
                'elementos' => [[
                    'id' => 'el_1',
                    'tipo' => 'texto',
                    'x' => 10,
                    'y' => 40,
                    'w' => 80,
                    'h' => 10,
                    'texto' => '{{nome_aluno}}',
                    'fonteTamanho' => 16,
                    'fontePeso' => 'normal',
                    'alinhamento' => 'center',
                    'cor' => '#18181b',
                ]],
            ]),
        ]));

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->putJson('/api/cursos/'.$this->curso->id, [
                'nome' => 'Violão',
                'descricao' => 'Cordas',
                'id_template_certificado' => $template->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.id_template_certificado', $template->id);

        $this->putJson('/api/cursos/'.$this->curso->id, [
            'nome' => 'Violão',
            'descricao' => 'Cordas',
            'id_template_certificado' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.id_template_certificado', null);
    }
}
