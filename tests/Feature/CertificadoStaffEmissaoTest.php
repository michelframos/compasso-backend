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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class CertificadoStaffEmissaoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $secretaria;

    private User $usuarioAna;

    private Aluno $ana;

    private Aluno $bruno;

    private Matricula $matriculaAna;

    private Matricula $matriculaBruno;

    private Matricula $matriculaSemFrequencia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria', ['nome' => 'Secretaria']);
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $usuarioCarla = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Carla Sem Freq']);
        $usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno, $usuarioCarla, $usuarioPaula): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $paula = Professor::create(['id_usuario' => $usuarioPaula->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $carla = Aluno::create(['id_usuario' => $usuarioCarla->id]);

            $turmaAna = $this->turma($curso, $nivel, $paula, 'Turma Ana');
            $turmaBruno = $this->turma($curso, $nivel, $paula, 'Turma Bruno');
            $turmaCarla = $this->turma($curso, $nivel, $paula, 'Turma Carla');

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
            $this->matriculaSemFrequencia = Matricula::create([
                'id_aluno' => $carla->id,
                'id_turma' => $turmaCarla->id,
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

            foreach (['2026-09-08', '2026-09-15', '2026-09-22', '2026-09-29'] as $data) {
                $aula = $this->aula($turmaBruno, $paula, $data);
                AulaPresenca::create([
                    'id_aula_turma' => $aula->id,
                    'id_aluno' => $this->bruno->id,
                    'status' => 'presente',
                ]);
            }
        });
    }

    public function test_lista_apenas_matriculas_realmente_elegiveis(): void
    {
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->getJson('/api/certificados/elegiveis')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['nome' => 'Ana Lima'])
            ->assertJsonFragment(['nome' => 'Bruno Melo'])
            ->assertJsonMissing(['nome' => 'Carla Sem Freq']);
    }

    public function test_emissao_em_lote_parcial_continua_nas_falhas(): void
    {
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/certificados/emitir-lote', [
                'matricula_ids' => [
                    $this->matriculaAna->id,
                    $this->matriculaSemFrequencia->id,
                    $this->matriculaBruno->id,
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.emitidos')
            ->assertJsonCount(1, 'data.falhas')
            ->assertJsonPath('data.falhas.0.id_matricula', $this->matriculaSemFrequencia->id);

        $this->assertDatabaseHas('certificados', ['id_matricula' => $this->matriculaAna->id]);
        $this->assertDatabaseHas('certificados', ['id_matricula' => $this->matriculaBruno->id]);
        $this->assertDatabaseMissing('certificados', ['id_matricula' => $this->matriculaSemFrequencia->id]);

        $this->getJson('/api/certificados/elegiveis')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_secretaria_baixa_pdf_do_certificado(): void
    {
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/matriculas/'.$this->matriculaAna->id.'/certificados')
            ->assertCreated();

        $certificado = Certificado::query()->where('id_matricula', $this->matriculaAna->id)->firstOrFail();

        $pdf = $this->get('/api/certificados/'.$certificado->id.'/pdf');
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
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
