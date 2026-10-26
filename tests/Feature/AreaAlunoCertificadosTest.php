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
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoCertificadosTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private User $secretaria;

    private Aluno $ana;

    private Aluno $bruno;

    private Matricula $matriculaAna;

    private Matricula $matriculaBruno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria', ['nome' => 'Secretaria']);
        $usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno, $usuarioPaula): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $paula = Professor::create(['id_usuario' => $usuarioPaula->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $turmaAna = $this->turma($curso, $nivel, $paula, 'Turma Ana');
            $turmaBruno = $this->turma($curso, $nivel, $paula, 'Turma Bruno');

            $this->matriculaAna = Matricula::create([
                'id_aluno' => $this->ana->id,
                'id_turma' => $turmaAna->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'completada',
            ]);
            $this->matriculaBruno = Matricula::create([
                'id_aluno' => $this->bruno->id,
                'id_turma' => $turmaBruno->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'completada',
            ]);

            foreach (['2026-09-08', '2026-09-15', '2026-09-22', '2026-09-29'] as $i => $data) {
                $aula = $this->aula($turmaAna, $paula, $data);
                AulaPresenca::create([
                    'id_aula_turma' => $aula->id,
                    'id_aluno' => $this->ana->id,
                    'status' => $i === 3 ? 'ausente' : 'presente',
                ]);
            }

            $aulaBruno = $this->aula($turmaBruno, $paula, '2026-09-10');
            AulaPresenca::create([
                'id_aula_turma' => $aulaBruno->id,
                'id_aluno' => $this->bruno->id,
                'status' => 'presente',
            ]);
        });
    }

    public function test_secretaria_emite_e_aluno_baixa_o_pdf_sem_ver_o_do_outro(): void
    {
        $this->matriculaAna->update(['status' => 'ativa']);
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/matriculas/'.$this->matriculaAna->id.'/certificados')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_matricula');

        $this->matriculaAna->update(['status' => 'completada']);
        $this->postJson('/api/matriculas/'.$this->matriculaAna->id.'/certificados')
            ->assertCreated()
            ->assertJsonPath('data.aluno.nome', 'Ana Lima')
            ->assertJsonPath('data.curso', 'Violão')
            ->assertJsonPath('data.carga_horaria', 3);

        $codigo = Certificado::query()->where('id_matricula', $this->matriculaAna->id)->value('codigo_verificacao');
        $this->assertNotEmpty($codigo);

        $this->postJson('/api/matriculas/'.$this->matriculaAna->id.'/certificados')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_matricula');

        $lista = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/certificados')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $lista);
        $this->assertSame($codigo, $lista[0]['codigo_verificacao']);
        $this->assertStringNotContainsString('Bruno Melo', json_encode($lista));

        $pdf = $this->get('/api/aluno/me/certificados/'.$lista[0]['id'].'/pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));

        $certificadoBruno = $this->naInstituicao($this->instituicao, fn () => Certificado::create([
            'id_matricula' => $this->matriculaBruno->id,
            'codigo_verificacao' => 'CMP-TEST-BRUN',
            'emitido_em' => now(),
            'carga_horaria' => 1,
        ]));

        $this->get('/api/aluno/me/certificados/'.$certificadoBruno->id.'/pdf')->assertForbidden();

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/certificados')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $this->getJson('/api/aluno/me/certificados?id_aluno='.$this->ana->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/aluno/me/certificados?id_aluno='.$this->bruno->id)->assertForbidden();

        $publico = $this->getJson('/api/public/certificados/'.$codigo)
            ->assertOk()
            ->json('data');

        $this->assertTrue($publico['valido']);
        $this->assertSame('Ana Lima', $publico['aluno']);
        $this->assertSame('Violão', $publico['curso']);
        $this->assertEquals(3, $publico['carga_horaria']);
        $this->assertStringNotContainsString('Bruno Melo', json_encode($publico));

        $this->getJson('/api/public/certificados/CMP-XXXX-YYYY')->assertNotFound();
    }

    public function test_frequencia_baixa_impede_emissao_e_demais_papeis_recebem_401_ou_403(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/certificados')
            ->assertUnauthorized();

        $this->naInstituicao($this->instituicao, function (): void {
            $presenca = AulaPresenca::query()->where('id_aluno', $this->ana->id)->where('status', 'presente')->first();
            $presenca->update(['status' => 'ausente']);
        });

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/matriculas/'.$this->matriculaAna->id.'/certificados')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_matricula');

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/certificados')
                ->assertForbidden();
        }

        $professor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $this->comoUsuario($professor, $this->instituicao)
            ->postJson('/api/matriculas/'.$this->matriculaAna->id.'/certificados')
            ->assertForbidden();

        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'aluno', ['nome' => 'Aluno de Fora']);
        $this->naInstituicao($outra, fn () => Aluno::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/aluno/me/certificados')
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

    private function aula(Turma $turma, Professor $professor, string $data): AulaTurma
    {
        return AulaTurma::create([
            'id_turma' => $turma->id,
            'id_curso' => $turma->id_curso,
            'id_professor' => $professor->id,
            'data' => $data,
            'hora_inicio' => '14:00',
            'hora_termino' => '15:00',
            'status' => 'concluida',
            'tipo' => 'regular',
        ]);
    }
}
