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
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class ReemitirCertificadoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $secretaria;

    private User $alunoUser;

    private Matricula $matricula;

    private Curso $curso;

    private TemplateCertificado $templateA;

    private TemplateCertificado $templateB;

    private Certificado $certificado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00');
        Storage::fake('public');

        $this->instituicao = $this->instituicaoDefault();
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria', ['nome' => 'Secretaria']);
        $this->alunoUser = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioPaula): void {
            $this->curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $paula = Professor::create(['id_usuario' => $usuarioPaula->id]);
            $ana = Aluno::create(['id_usuario' => $this->alunoUser->id]);

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

            $this->matricula = Matricula::create([
                'id_aluno' => $ana->id,
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
                    'id_aluno' => $ana->id,
                    'status' => $i === 3 ? 'ausente' : 'presente',
                ]);
            }

            $this->templateA = TemplateCertificado::create([
                'nome' => 'Template A',
                'conteudo' => LayoutCertificado::encode([
                    'version' => 1,
                    'elementos' => [[
                        'id' => 'el_1',
                        'tipo' => 'texto',
                        'x' => 10,
                        'y' => 35,
                        'w' => 80,
                        'h' => 12,
                        'texto' => 'Versao A {{nome_aluno}}',
                        'fonteTamanho' => 18,
                        'fontePeso' => 'bold',
                        'alinhamento' => 'center',
                        'cor' => '#18181b',
                    ]],
                ]),
            ]);

            $this->templateB = TemplateCertificado::create([
                'nome' => 'Template B',
                'conteudo' => LayoutCertificado::encode([
                    'version' => 1,
                    'elementos' => [[
                        'id' => 'el_1',
                        'tipo' => 'texto',
                        'x' => 10,
                        'y' => 35,
                        'w' => 80,
                        'h' => 12,
                        'texto' => 'Versao B {{nome_aluno}} codigo {{codigo_verificacao}}',
                        'fonteTamanho' => 18,
                        'fontePeso' => 'bold',
                        'alinhamento' => 'center',
                        'cor' => '#18181b',
                    ]],
                ]),
            ]);

            $this->curso->update(['id_template_certificado' => $this->templateA->id]);
        });

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/matriculas/'.$this->matricula->id.'/certificados')
            ->assertCreated();

        $this->certificado = Certificado::query()->where('id_matricula', $this->matricula->id)->firstOrFail();
    }

    public function test_reemitir_mantem_codigo_e_troca_template(): void
    {
        $codigoOriginal = $this->certificado->codigo_verificacao;
        $conteudoOriginal = $this->certificado->conteudo_gerado;

        $this->assertStringContainsString('Versao A', (string) $conteudoOriginal);

        $this->travelTo('2026-10-27 10:00:00');

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/certificados/'.$this->certificado->id.'/reemitir', [
                'id_template_certificado' => $this->templateB->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.codigo_verificacao', $codigoOriginal)
            ->assertJsonPath('data.id_matricula', $this->matricula->id);

        $this->certificado->refresh();
        $this->assertSame($codigoOriginal, $this->certificado->codigo_verificacao);
        $this->assertNotSame($conteudoOriginal, $this->certificado->conteudo_gerado);
        $this->assertStringContainsString('Versao B', (string) $this->certificado->conteudo_gerado);
        $this->assertStringContainsString($codigoOriginal, (string) $this->certificado->conteudo_gerado);
        $this->assertTrue($this->certificado->emitido_em->equalTo('2026-10-27 10:00:00'));

        $pdf = $this->get('/api/certificados/'.$this->certificado->id.'/pdf');
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_aluno_nao_pode_reemitir(): void
    {
        $this->comoUsuario($this->alunoUser, $this->instituicao)
            ->postJson('/api/certificados/'.$this->certificado->id.'/reemitir', [
                'id_template_certificado' => $this->templateB->id,
            ])
            ->assertForbidden();
    }

    public function test_template_de_outra_instituicao_retorna_422(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola-reemitir',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        $templateOutro = $this->naInstituicao($outra, fn () => TemplateCertificado::create([
            'nome' => 'Template Fora',
            'conteudo' => LayoutCertificado::encode([
                'version' => 1,
                'elementos' => [[
                    'id' => 'el_1',
                    'tipo' => 'texto',
                    'x' => 10,
                    'y' => 35,
                    'w' => 80,
                    'h' => 12,
                    'texto' => 'Fora',
                    'fonteTamanho' => 18,
                    'fontePeso' => 'bold',
                    'alinhamento' => 'center',
                    'cor' => '#18181b',
                ]],
            ]),
        ]));

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/certificados/'.$this->certificado->id.'/reemitir', [
                'id_template_certificado' => $templateOutro->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_template_certificado');
    }
}
