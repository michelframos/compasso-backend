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

class AreaProfessorChamadaDiarioTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioProfessor;

    private Turma $turma;

    private Turma $turmaAlheia;

    private AulaTurma $aulaOntem;

    private AulaTurma $aulaAlheia;

    /** @var list<int> */
    private array $alunos = [];

    private int $alunoDeOutraTurma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-30 15:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuarioOutroProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuariosAlunos = [
            $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno'),
            $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno'),
            $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno'),
        ];

        $this->naInstituicao($this->instituicao, function () use ($usuarioOutroProfessor, $usuariosAlunos): void {
            $curso = Curso::create(['nome' => 'Piano', 'descricao' => 'Teclas']);
            $nivel = Nivel::create(['nome' => 'Básico']);

            $professor = Professor::create(['id_usuario' => $this->usuarioProfessor->id, 'valor_hora_aula' => 50, 'comissao' => 30]);
            $outroProfessor = Professor::create(['id_usuario' => $usuarioOutroProfessor->id]);

            $this->turma = $this->criarTurma($curso, $nivel, $professor);
            $this->turmaAlheia = $this->criarTurma($curso, $nivel, $outroProfessor);

            $alunos = array_map(fn (User $u) => Aluno::create(['id_usuario' => $u->id]), $usuariosAlunos);
            $this->matricular($alunos[0], $this->turma);
            $this->matricular($alunos[1], $this->turma);
            $this->matricular($alunos[2], $this->turmaAlheia);
            $this->alunos = [$alunos[0]->id, $alunos[1]->id];
            $this->alunoDeOutraTurma = $alunos[2]->id;

            $this->aulaOntem = $this->criarAula($this->turma, '2026-09-29');
            $this->aulaAlheia = $this->criarAula($this->turmaAlheia, '2026-09-29');
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
            'valor_hora_aula_especifico' => 70,
        ]);
    }

    private function matricular(Aluno $aluno, Turma $turma): void
    {
        Matricula::create([
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
            'tipo' => 'turma',
            'data' => '2026-09-01',
            'status' => 'ativa',
        ]);
    }

    private function criarAula(Turma $turma, string $data, string $status = 'agendada'): AulaTurma
    {
        return AulaTurma::create([
            'id_turma' => $turma->id,
            'id_professor' => $turma->id_professor,
            'data' => $data,
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'status' => $status,
        ]);
    }

    private function chamada(string $conteudo = 'Escala de Dó maior'): array
    {
        return [
            'conteudo_dado' => $conteudo,
            'presencas' => [
                ['id_aluno' => $this->alunos[0], 'status' => 'presente'],
                ['id_aluno' => $this->alunos[1], 'status' => 'ausente', 'observacao' => 'Avisou que estava doente'],
            ],
        ];
    }

    private function comoProfessor(): static
    {
        return $this->comoUsuario($this->usuarioProfessor, $this->instituicao);
    }

    public function test_professor_conclui_aula_com_chamada_conteudo_e_snapshot(): void
    {
        $this->naInstituicao($this->instituicao, fn () => AulaPresenca::create([
            'id_aula_turma' => $this->aulaOntem->id,
            'id_aluno' => $this->alunos[0],
            'status' => 'ausente',
        ]));

        $this->comoProfessor()
            ->postJson("/api/aulas-turmas/{$this->aulaOntem->id}/concluir", $this->chamada())
            ->assertOk()
            ->assertJsonPath('data.status', 'concluida')
            ->assertJsonPath('data.presencas_count', 2)
            ->assertJsonMissingPath('data.valor_hora_aula_aplicado');

        $aula = AulaTurma::withoutGlobalScopes()->find($this->aulaOntem->id);
        $this->assertSame('concluida', $aula->status);
        $this->assertSame('Escala de Dó maior', $aula->conteudo_dado);
        $this->assertEquals(70, $aula->valor_hora_aula_aplicado);
        $this->assertEquals(30, $aula->percentual_comissao_aplicado);
        $this->assertEquals(300, $aula->valor_mensalidade_aplicado);

        $presencas = AulaPresenca::withoutGlobalScopes()->where('id_aula_turma', $aula->id)->pluck('status', 'id_aluno');
        $this->assertCount(2, $presencas);
        $this->assertSame('presente', $presencas[$this->alunos[0]]);
        $this->assertSame('ausente', $presencas[$this->alunos[1]]);
    }

    public function test_aula_concluida_so_pode_ser_alterada_por_admin(): void
    {
        $this->comoProfessor()
            ->postJson("/api/aulas-turmas/{$this->aulaOntem->id}/concluir", $this->chamada())
            ->assertOk();

        $this->postJson("/api/aulas-turmas/{$this->aulaOntem->id}/concluir", $this->chamada('Outro conteúdo'))
            ->assertForbidden();

        $admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $this->comoUsuario($admin, $this->instituicao)
            ->postJson("/api/aulas-turmas/{$this->aulaOntem->id}/concluir", $this->chamada('Corrigido pelo admin'))
            ->assertOk();

        $this->assertSame('Corrigido pelo admin', AulaTurma::withoutGlobalScopes()->find($this->aulaOntem->id)->conteudo_dado);
    }

    public function test_professor_nao_conclui_aula_de_turma_alheia(): void
    {
        $this->comoProfessor()
            ->postJson("/api/aulas-turmas/{$this->aulaAlheia->id}/concluir", ['presencas' => []])
            ->assertForbidden();

        $this->assertSame('agendada', AulaTurma::withoutGlobalScopes()->find($this->aulaAlheia->id)->status);
    }

    public function test_regras_de_negocio_ao_concluir(): void
    {
        [$futura, $cancelada] = $this->naInstituicao($this->instituicao, fn () => [
            $this->criarAula($this->turma, '2026-10-01'),
            $this->criarAula($this->turma, '2026-09-28', 'cancelada'),
        ]);

        $this->comoProfessor();

        $this->postJson("/api/aulas-turmas/{$futura->id}/concluir", $this->chamada())
            ->assertUnprocessable()->assertJsonValidationErrors('aula');
        $this->postJson("/api/aulas-turmas/{$cancelada->id}/concluir", $this->chamada())
            ->assertUnprocessable()->assertJsonValidationErrors('aula');

        $this->postJson("/api/aulas-turmas/{$this->aulaOntem->id}/concluir", [
            'presencas' => [['id_aluno' => $this->alunoDeOutraTurma, 'status' => 'presente']],
        ])->assertUnprocessable()->assertJsonValidationErrors('presencas');

        $this->postJson("/api/aulas-turmas/{$this->aulaOntem->id}/concluir", [
            'presencas' => [['id_aluno' => $this->alunos[0], 'status' => 'falta']],
        ])->assertUnprocessable()->assertJsonValidationErrors('presencas.0.status');

        $this->naInstituicao($this->instituicao, fn () => $this->turma->update(['status' => TurmaStatus::CONCLUIDA]));
        $this->postJson("/api/aulas-turmas/{$this->aulaOntem->id}/concluir", $this->chamada())->assertForbidden();

        $this->assertSame('agendada', AulaTurma::withoutGlobalScopes()->find($this->aulaOntem->id)->status);
    }

    public function test_detalhe_da_aula_para_chamada(): void
    {
        $this->naInstituicao($this->instituicao, fn () => AulaPresenca::create([
            'id_aula_turma' => $this->aulaOntem->id,
            'id_aluno' => $this->alunos[1],
            'status' => 'justificado',
        ]));

        $this->comoProfessor()
            ->getJson("/api/professor/me/aulas/{$this->aulaOntem->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.turma.matriculas')
            ->assertJsonPath('data.presencas.0.id_aluno', $this->alunos[1])
            ->assertJsonPath('data.presencas.0.status', 'justificado');

        $this->getJson("/api/professor/me/aulas/{$this->aulaAlheia->id}")->assertForbidden();
    }

    public function test_diario_da_turma_do_professor(): void
    {
        $this->comoProfessor()
            ->postJson("/api/aulas-turmas/{$this->aulaOntem->id}/concluir", $this->chamada())
            ->assertOk();
        $this->naInstituicao($this->instituicao, fn () => $this->criarAula($this->turma, '2026-09-28'));

        $this->getJson("/api/professor/me/turmas/{$this->turma->id}/diario?data_inicio=2026-09-01&data_fim=2026-09-30")
            ->assertOk()
            ->assertJsonPath('data.curso', 'Piano')
            ->assertJsonCount(2, 'data.alunos')
            ->assertJsonCount(1, 'data.aulas')
            ->assertJsonPath('data.aulas.0.data', '2026-09-29')
            ->assertJsonPath('data.aulas.0.conteudo_dado', 'Escala de Dó maior')
            ->assertJsonPath("data.aulas.0.presencas.{$this->alunos[0]}", 'presente')
            ->assertJsonPath("data.aulas.0.presencas.{$this->alunos[1]}", 'falta');

        $this->getJson("/api/professor/me/turmas/{$this->turma->id}/diario")
            ->assertOk()
            ->assertJsonPath('data.data_inicio', '2026-09-01')
            ->assertJsonPath('data.data_fim', '2026-09-30');

        $this->getJson("/api/professor/me/turmas/{$this->turma->id}/diario?data_inicio=2026-09-30&data_fim=2026-09-01")
            ->assertUnprocessable();
        $this->getJson("/api/professor/me/turmas/{$this->turmaAlheia->id}/diario")->assertForbidden();

        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $this->comoUsuario($secretaria, $this->instituicao)
            ->getJson("/api/professor/me/turmas/{$this->turma->id}/diario")
            ->assertForbidden();
    }

    public function test_isolamento_entre_instituicoes(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'professor');

        [$turmaDeFora, $aulaDeFora] = $this->naInstituicao($outra, function () use ($usuarioOutro): array {
            $turma = $this->criarTurma(
                Curso::create(['nome' => 'Bateria', 'descricao' => 'Percussão']),
                Nivel::create(['nome' => 'Avançado']),
                Professor::create(['id_usuario' => $usuarioOutro->id])
            );

            return [$turma, $this->criarAula($turma, '2026-09-29')];
        });

        $this->comoProfessor();
        $this->postJson("/api/aulas-turmas/{$aulaDeFora->id}/concluir", ['presencas' => []])->assertNotFound();
        $this->getJson("/api/professor/me/aulas/{$aulaDeFora->id}")->assertNotFound();
        $this->getJson("/api/professor/me/turmas/{$turmaDeFora->id}/diario")->assertNotFound();
    }
}
