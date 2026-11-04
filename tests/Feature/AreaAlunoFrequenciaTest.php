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
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoFrequenciaTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $bruno;

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

            $turmaOrigem = $this->turma($curso, $nivel, $paula, 'Turma origem');
            $turmaAna = $this->turma($curso, $nivel, $paula, 'Turma Ana');
            $turmaAntiga = $this->turma($curso, $nivel, $paula, 'Turma antiga');
            $turmaBruno = $this->turma($curso, $nivel, $paula, 'Turma Bruno');

            $this->matriculaAna = Matricula::create([
                'id_aluno' => $this->ana->id,
                'id_turma' => $turmaOrigem->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'ativa',
            ]);
            $this->matriculaAna->motivoTransferencia = 'Mudança de horário';
            $this->matriculaAna->update(['id_turma' => $turmaAna->id]);

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

            $this->chamada($this->aula($turmaOrigem, $paula, '2026-09-02', '09:00'), $this->ana, 'presente');
            $this->chamada($this->aula($turmaAna, $paula, '2026-10-06', '14:00'), $this->ana, 'presente');
            $this->chamada($this->aula($turmaAna, $paula, '2026-10-13', '14:00'), $this->ana, 'ausente');
            $this->chamada($this->aula($turmaAna, $paula, '2026-10-20', '14:00', conteudo: 'conteudo secreto'), $this->ana, 'justificado', 'nota interna do professor');

            $cancelada = $this->aula($turmaAna, $paula, '2026-10-27', '07:00', 'cancelada');
            $this->chamada($cancelada, $this->ana, 'ausente');

            $doColega = $this->aula($turmaAna, $paula, '2026-10-21', '08:00', 'concluida', $this->bruno->id);
            $this->chamada($doColega, $this->ana, 'presente');

            $individual = AulaTurma::create([
                'id_curso' => $curso->id,
                'id_professor' => $otto->id,
                'data' => '2026-10-08',
                'hora_inicio' => '16:00',
                'hora_termino' => '17:00',
                'status' => 'concluida',
                'tipo' => 'regular',
                'id_aluno_especifico' => $this->ana->id,
            ]);
            $this->chamada($individual, $this->ana, 'presente');

            $this->chamada($this->aula($turmaAntiga, $paula, '2026-02-02', '11:00'), $this->ana, 'ausente');
            $this->chamada($this->aula($turmaBruno, $paula, '2026-10-09', '18:00'), $this->ana, 'presente');
            $this->chamada($this->aula($turmaBruno, $paula, '2026-10-09', '18:00'), $this->bruno, 'ausente', 'falta do bruno');
        });
    }

    public function test_aluno_ve_presencas_faltas_e_percentual_por_turma(): void
    {
        $resposta = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/frequencia')
            ->assertOk();

        $dados = $resposta->json('data');

        $this->assertSame(6, $dados['resumo']['total']);
        $this->assertSame(3, $dados['resumo']['presentes']);
        $this->assertSame(2, $dados['resumo']['ausentes']);
        $this->assertSame(1, $dados['resumo']['justificados']);
        $this->assertEquals(50, $dados['resumo']['percentual']);

        $this->assertCount(3, $dados['turmas']);
        $this->assertSame('Turma Ana', $dados['turmas'][0]['nome']);
        $this->assertSame('Violão', $dados['turmas'][0]['curso']);
        $this->assertSame(4, $dados['turmas'][0]['total']);
        $this->assertSame(2, $dados['turmas'][0]['presentes']);
        $this->assertSame(1, $dados['turmas'][0]['ausentes']);
        $this->assertSame(1, $dados['turmas'][0]['justificados']);
        $this->assertEquals(50, $dados['turmas'][0]['percentual']);
        $this->assertSame('2026-10-20', $dados['turmas'][0]['aulas'][0]['data']);
        $this->assertSame('14:00', $dados['turmas'][0]['aulas'][0]['hora_inicio']);
        $this->assertSame('justificado', $dados['turmas'][0]['aulas'][0]['status']);
        $this->assertSame('2026-09-02', $dados['turmas'][0]['aulas'][3]['data']);

        $this->assertSame('Violão', $dados['turmas'][1]['nome']);
        $this->assertNull($dados['turmas'][1]['id_turma']);
        $this->assertSame(1, $dados['turmas'][1]['presentes']);
        $this->assertSame('2026-10-08', $dados['turmas'][1]['aulas'][0]['data']);

        $this->assertSame('Turma antiga', $dados['turmas'][2]['nome']);
        $this->assertSame('cancelada', $dados['turmas'][2]['status_matricula']);
        $this->assertSame(1, $dados['turmas'][2]['ausentes']);

        $corpo = $resposta->getContent();
        $this->assertStringNotContainsString('nota interna do professor', $corpo);
        $this->assertStringNotContainsString('conteudo secreto', $corpo);
        $this->assertStringNotContainsString('falta do bruno', $corpo);
        $this->assertStringNotContainsString('Turma Bruno', $corpo);
        $this->assertStringNotContainsString('07:00', $corpo);
        $this->assertStringNotContainsString('08:00', $corpo);
        $this->assertStringNotContainsString('18:00', $corpo);
        $this->assertStringNotContainsString('observacao', $corpo);

        $filtrada = $this->getJson('/api/aluno/me/frequencia?id_matricula='.$this->matriculaAna->id)
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $filtrada['turmas']);
        $this->assertSame('Turma Ana', $filtrada['turmas'][0]['nome']);
        $this->assertSame(4, $filtrada['resumo']['total']);
        $this->assertEquals(50, $filtrada['resumo']['percentual']);

        $this->getJson('/api/aluno/me/frequencia?id_matricula=abc')->assertStatus(422);
    }

    public function test_nao_ve_frequencia_de_outro_aluno(): void
    {
        $idBruno = Matricula::query()->where('id_aluno', $this->bruno->id)->value('id');

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/frequencia?id_matricula='.$idBruno)
            ->assertForbidden();

        $this->getJson('/api/aluno/me/frequencia?id_matricula=99999')->assertNotFound();
    }

    public function test_responsavel_consulta_o_dependente_e_os_demais_papeis_nao(): void
    {
        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/frequencia')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/frequencia?id_aluno='.$this->ana->id)
            ->assertOk()
            ->assertJsonPath('data.turmas.0.nome', 'Turma Ana')
            ->assertJsonPath('data.resumo.presentes', 3);

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/frequencia?id_aluno='.$this->bruno->id)
            ->assertForbidden();

        $idBruno = Matricula::query()->where('id_aluno', $this->bruno->id)->value('id');
        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/frequencia?id_aluno='.$this->ana->id.'&id_matricula='.$idBruno)
            ->assertForbidden();

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/frequencia')
                ->assertForbidden();
        }
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/frequencia')
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
            ->getJson('/api/aluno/me/frequencia')
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

    private function aula(
        Turma $turma,
        Professor $professor,
        string $data,
        string $hora,
        string $status = 'concluida',
        ?int $idAlunoEspecifico = null,
        ?string $conteudo = null,
    ): AulaTurma {
        return AulaTurma::create([
            'id_turma' => $turma->id,
            'id_curso' => $turma->id_curso,
            'id_professor' => $professor->id,
            'data' => $data,
            'hora_inicio' => $hora,
            'hora_termino' => '15:00',
            'status' => $status,
            'tipo' => 'regular',
            'id_aluno_especifico' => $idAlunoEspecifico,
            'conteudo_dado' => $conteudo,
        ]);
    }

    private function chamada(AulaTurma $aula, Aluno $aluno, string $status, ?string $observacao = null): void
    {
        AulaPresenca::create([
            'id_aula_turma' => $aula->id,
            'id_aluno' => $aluno->id,
            'status' => $status,
            'observacao' => $observacao,
        ]);
    }
}
