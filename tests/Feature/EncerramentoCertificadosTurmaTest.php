<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class EncerramentoCertificadosTurmaTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $secretaria;

    private Professor $professor;

    private Curso $curso;

    private Nivel $nivel;

    private Turma $turma;

    private Aluno $ana;

    private Aluno $bruno;

    private Aluno $carla;

    private Matricula $matriculaAna;

    private Matricula $matriculaBruno;

    private Matricula $matriculaCarla;

    private Matricula $matriculaCancelada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria', ['nome' => 'Secretaria']);
        $usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $usuarioCarla = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Carla Sem Freq']);
        $usuarioDiego = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Diego Cancelado']);
        $usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioAna, $usuarioBruno, $usuarioCarla, $usuarioDiego, $usuarioPaula): void {
            $this->curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $this->nivel = Nivel::create(['nome' => 'Básico']);
            $this->professor = Professor::create(['id_usuario' => $usuarioPaula->id]);

            $this->turma = $this->turma('Turma Encerramento', TurmaStatus::CONCLUIDA);

            $this->ana = Aluno::create(['id_usuario' => $usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $this->carla = Aluno::create(['id_usuario' => $usuarioCarla->id]);
            $diego = Aluno::create(['id_usuario' => $usuarioDiego->id]);

            $this->matriculaAna = Matricula::create([
                'id_aluno' => $this->ana->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'ativa',
            ]);
            $this->matriculaBruno = Matricula::create([
                'id_aluno' => $this->bruno->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'completada',
            ]);
            $this->matriculaCarla = Matricula::create([
                'id_aluno' => $this->carla->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'ativa',
            ]);
            $this->matriculaCancelada = Matricula::create([
                'id_aluno' => $diego->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'cancelada',
            ]);

            foreach (['2026-09-08', '2026-09-15', '2026-09-22', '2026-09-29'] as $i => $data) {
                $aula = $this->aula($data);
                AulaPresenca::create([
                    'id_aula_turma' => $aula->id,
                    'id_aluno' => $this->ana->id,
                    'status' => $i === 3 ? 'ausente' : 'presente',
                ]);
                AulaPresenca::create([
                    'id_aula_turma' => $aula->id,
                    'id_aluno' => $this->bruno->id,
                    'status' => 'presente',
                ]);
                AulaPresenca::create([
                    'id_aula_turma' => $aula->id,
                    'id_aluno' => $diego->id,
                    'status' => 'presente',
                ]);
            }
        });
    }

    public function test_preview_separa_aptos_inaptos_e_ignora_cancelada(): void
    {
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->getJson('/api/turmas/'.$this->turma->id.'/encerramento-certificados')
            ->assertOk()
            ->assertJsonCount(2, 'data.aptos')
            ->assertJsonCount(1, 'data.inaptos')
            ->assertJsonFragment(['nome' => 'Ana Lima'])
            ->assertJsonFragment(['nome' => 'Bruno Melo'])
            ->assertJsonFragment(['nome' => 'Carla Sem Freq'])
            ->assertJsonMissing(['nome' => 'Diego Cancelado']);
    }

    public function test_emissao_completa_matricula_ativa_e_emite(): void
    {
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/turmas/'.$this->turma->id.'/encerramento-certificados', [
                'matricula_ids' => [$this->matriculaAna->id],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.emitidos')
            ->assertJsonCount(0, 'data.falhas');

        $this->assertDatabaseHas('matriculas', [
            'id' => $this->matriculaAna->id,
            'status' => 'completada',
        ]);
        $this->assertDatabaseHas('certificados', [
            'id_matricula' => $this->matriculaAna->id,
        ]);
    }

    public function test_emissao_falha_se_turma_nao_esta_concluida(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->turma->update(['status' => TurmaStatus::EM_ANDAMENTO]);
        });

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/turmas/'.$this->turma->id.'/encerramento-certificados', [
                'matricula_ids' => [$this->matriculaAna->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('turma');

        $this->assertDatabaseMissing('certificados', [
            'id_matricula' => $this->matriculaAna->id,
        ]);
    }

    public function test_emissao_parcial_continua_nas_falhas(): void
    {
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->postJson('/api/turmas/'.$this->turma->id.'/encerramento-certificados', [
                'matricula_ids' => [
                    $this->matriculaAna->id,
                    $this->matriculaCarla->id,
                    $this->matriculaBruno->id,
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.emitidos')
            ->assertJsonCount(1, 'data.falhas')
            ->assertJsonPath('data.falhas.0.id_matricula', $this->matriculaCarla->id);

        $this->assertDatabaseHas('certificados', ['id_matricula' => $this->matriculaAna->id]);
        $this->assertDatabaseHas('certificados', ['id_matricula' => $this->matriculaBruno->id]);
        $this->assertDatabaseMissing('certificados', ['id_matricula' => $this->matriculaCarla->id]);
        $this->assertDatabaseHas('matriculas', [
            'id' => $this->matriculaAna->id,
            'status' => 'completada',
        ]);
    }

    private function turma(string $descricao, TurmaStatus $status): Turma
    {
        return Turma::create([
            'id_curso' => $this->curso->id,
            'id_nivel' => $this->nivel->id,
            'id_professor' => $this->professor->id,
            'descricao' => $descricao,
            'maximo_alunos' => 10,
            'status' => $status,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => '2026-09-01',
            'valor_mensalidade' => 350,
        ]);
    }

    private function aula(string $data): AulaTurma
    {
        return AulaTurma::create([
            'id_turma' => $this->turma->id,
            'id_curso' => $this->curso->id,
            'id_professor' => $this->professor->id,
            'data' => $data,
            'hora_inicio' => '14:00',
            'hora_termino' => '15:00',
            'status' => 'concluida',
            'tipo' => 'regular',
        ]);
    }
}
