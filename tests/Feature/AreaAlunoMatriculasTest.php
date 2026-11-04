<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Models\TurmaHorario;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoMatriculasTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $bruno;

    private Turma $turmaNova;

    private Matricula $matriculaAna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);
        $usuarioOtto = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Otto Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno, $usuarioPaula, $usuarioOtto): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $paula = Professor::create(['id_usuario' => $usuarioPaula->id]);
            $otto = Professor::create(['id_usuario' => $usuarioOtto->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $turmaAna = $this->turma($curso, $nivel, $paula, 'Turma Ana', '2026-12-15');
            $turmaBruno = $this->turma($curso, $nivel, $paula, 'Turma Bruno');
            $turmaAntiga = $this->turma($curso, $nivel, $paula, 'Turma antiga');
            $this->turmaNova = $this->turma($curso, $nivel, $paula, 'Turma nova');

            TurmaHorario::create([
                'id_turma' => $turmaAna->id,
                'dia_semana' => 'terca',
                'hora_inicio' => '14:00',
                'hora_termino' => '15:00',
            ]);

            $this->matriculaAna = Matricula::create([
                'id_aluno' => $this->ana->id,
                'id_turma' => $turmaAna->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'ativa',
                'observacoes' => 'segredo da secretaria',
            ]);
            Matricula::create([
                'id_aluno' => $this->ana->id,
                'tipo' => 'curso',
                'id_curso' => $curso->id,
                'id_nivel' => $nivel->id,
                'id_professor' => $otto->id,
                'data' => '2026-08-15',
                'status' => 'ativa',
            ]);
            Matricula::create([
                'id_aluno' => $this->ana->id,
                'id_turma' => $turmaAntiga->id,
                'tipo' => 'turma',
                'data' => '2026-01-10',
                'status' => 'cancelada',
            ]);
            Matricula::create([
                'id_aluno' => $this->bruno->id,
                'id_turma' => $turmaBruno->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'ativa',
            ]);
        });
    }

    public function test_aluno_ve_as_matriculas_vigentes_e_o_historico_das_encerradas(): void
    {
        $lista = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/matriculas')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $lista);
        $this->assertSame('Turma Ana', $lista[0]['turma']['nome']);
        $this->assertSame('Violão', $lista[0]['curso']['nome']);
        $this->assertSame('Básico', $lista[0]['nivel']['nome']);
        $this->assertSame('Paula Prof', $lista[0]['professor']['nome']);
        $this->assertSame('terca', $lista[0]['horarios'][0]['dia_semana']);
        $this->assertSame('14:00', $lista[0]['horarios'][0]['hora_inicio']);
        $this->assertSame('15:00', $lista[0]['horarios'][0]['hora_termino']);
        $this->assertSame('2026-09-01', $lista[0]['periodo']['inicio']);
        $this->assertSame('2026-12-15', $lista[0]['periodo']['fim']);
        $this->assertArrayNotHasKey('observacoes', $lista[0]);
        $this->assertArrayNotHasKey('valor_mensalidade', $lista[0]);
        $this->assertArrayNotHasKey('valor_mensalidade', $lista[0]['turma']);

        $this->assertNull($lista[1]['turma']);
        $this->assertSame('Otto Prof', $lista[1]['professor']['nome']);
        $this->assertSame([], $lista[1]['horarios']);
        $this->assertSame('2026-08-15', $lista[1]['periodo']['inicio']);

        $encerradas = $this->getJson('/api/aluno/me/matriculas?status=encerradas')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $encerradas);
        $this->assertSame('Turma antiga', $encerradas[0]['turma']['nome']);
        $this->assertSame('cancelada', $encerradas[0]['status']);

        $this->getJson('/api/aluno/me/matriculas?status=todas')->assertStatus(422);
    }

    public function test_historico_mostra_a_matricula_inicial_e_a_transferencia(): void
    {
        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson("/api/aluno/me/matriculas/{$this->matriculaAna->id}/historico")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.motivo', 'Matrícula inicial')
            ->assertJsonPath('data.0.id_turma_destino', $this->matriculaAna->id_turma);

        $this->naInstituicao($this->instituicao, function (): void {
            $matricula = Matricula::findOrFail($this->matriculaAna->id);
            $matricula->motivoTransferencia = 'Mudança de horário';
            $matricula->update(['id_turma' => $this->turmaNova->id]);
        });

        $historico = $this->getJson("/api/aluno/me/matriculas/{$this->matriculaAna->id}/historico")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $historico);
        $this->assertSame('Mudança de horário', $historico[0]['motivo']);
        $this->assertSame($this->turmaNova->id, $historico[0]['id_turma_destino']);
        $this->assertSame($this->matriculaAna->id_turma, $historico[0]['id_turma_origem']);
        $this->assertSame('Matrícula inicial', $historico[1]['motivo']);
    }

    public function test_nao_ve_matricula_de_outro_aluno(): void
    {
        $idBruno = Matricula::query()->where('id_aluno', $this->bruno->id)->value('id');

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/matriculas')
            ->assertOk()
            ->assertJsonMissing(['turma' => ['nome' => 'Turma Bruno']]);

        $this->getJson("/api/aluno/me/matriculas/{$idBruno}/historico")->assertForbidden();
        $this->getJson('/api/aluno/me/matriculas/99999/historico')->assertNotFound();
    }

    public function test_responsavel_consulta_o_dependente_e_os_demais_papeis_nao(): void
    {
        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/matriculas')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/matriculas?id_aluno='.$this->ana->id)
            ->assertOk()
            ->assertJsonPath('data.0.turma.nome', 'Turma Ana')
            ->assertJsonCount(2, 'data');

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson("/api/aluno/me/matriculas/{$this->matriculaAna->id}/historico?id_aluno={$this->ana->id}")
            ->assertOk()
            ->assertJsonPath('data.0.motivo', 'Matrícula inicial');

        $idBruno = Matricula::query()->where('id_aluno', $this->bruno->id)->value('id');
        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/matriculas?id_aluno='.$this->bruno->id)
            ->assertForbidden();
        $this->getJson("/api/aluno/me/matriculas/{$idBruno}/historico?id_aluno={$this->ana->id}")
            ->assertForbidden();

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/matriculas')
                ->assertForbidden();
        }
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/matriculas')
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
            ->getJson('/api/aluno/me/matriculas')
            ->assertForbidden();
    }

    private function turma(Curso $curso, Nivel $nivel, Professor $professor, string $descricao, ?string $fim = null): Turma
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
            'data_fim' => $fim,
            'valor_mensalidade' => 350,
        ]);
    }
}
