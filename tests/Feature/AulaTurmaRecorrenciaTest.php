<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AulaTurmaRecorrenciaTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $admin;

    private Turma $turma;

    private Professor $professor;

    private Curso $curso;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');

        $this->naInstituicao($this->instituicao, function (): void {
            $this->curso = Curso::create(['nome' => 'Piano', 'descricao' => 'Teclas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $this->professor = Professor::create([
                'id_usuario' => User::factory()->create(['role' => 'professor'])->id,
                'comissao' => 10,
            ]);
            $this->turma = Turma::create([
                'id_curso' => $this->curso->id,
                'id_nivel' => $nivel->id,
                'id_professor' => $this->professor->id,
                'maximo_alunos' => 10,
                'status' => TurmaStatus::ABERTA,
                'tipo_agendamento' => 'quantidade',
                'quantidade_aulas' => 10,
                'data_inicio' => now()->toDateString(),
            ]);
        });
    }

    private function datasDasAulas(): array
    {
        return AulaTurma::withoutInstituicaoScope()->orderBy('data')->get()
            ->map(fn ($a) => $a->data->toDateString())->all();
    }

    public function test_aula_unica(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/aulas-turmas', [
                'id_turma' => $this->turma->id,
                'id_professor' => $this->professor->id,
                'data' => '2026-03-02',
                'hora_inicio' => '14:00',
                'hora_termino' => '15:00',
            ])
            ->assertCreated();

        $this->assertSame(['2026-03-02'], $this->datasDasAulas());
    }

    public function test_aulas_recorrentes_quinzenais(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/aulas-turmas', [
                'id_turma' => $this->turma->id,
                'id_professor' => $this->professor->id,
                'data' => '2026-03-02',
                'hora_inicio' => '14:00',
                'hora_termino' => '15:00',
                'recorrente' => true,
                'quantidade_ocorrencias' => 3,
                'frequencia_ocorrencias' => 'quinzenal',
            ])
            ->assertCreated();

        $this->assertSame(['2026-03-02', '2026-03-16', '2026-03-30'], $this->datasDasAulas());
    }

    public function test_aulas_recorrentes_mensais_sem_overflow_com_conta(): void
    {
        [$aluno, $categoria] = $this->naInstituicao($this->instituicao, fn () => [
            Aluno::create(['id_usuario' => User::factory()->create(['role' => 'aluno'])->id]),
            CategoriaConta::create(['nome' => 'Aulas', 'tipo' => 'receita']),
        ]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/aulas-turmas', [
                'id_curso' => $this->curso->id,
                'id_aluno_especifico' => $aluno->id,
                'id_professor' => $this->professor->id,
                'data' => '2026-01-31',
                'hora_inicio' => '14:00',
                'hora_termino' => '15:00',
                'tipo' => 'reforco',
                'recorrente' => true,
                'quantidade_ocorrencias' => 3,
                'frequencia_ocorrencias' => 'mensal',
                'gerar_conta' => true,
                'valor_conta' => 80,
                'data_vencimento_conta' => '2026-02-05',
                'id_categoria_conta' => $categoria->id,
            ])
            ->assertCreated();

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $this->datasDasAulas());

        $contas = Conta::withoutInstituicaoScope()->orderBy('data_vencimento')->get();
        $this->assertCount(3, $contas);
        $this->assertSame(['2026-02-05', '2026-03-05', '2026-04-05'], $contas->map(fn ($c) => $c->data_vencimento->toDateString())->all());
        $this->assertSame('Agendamento de Aula (reforco) - 31/01/2026', $contas->first()->descricao);
        $this->assertEquals(80, $contas->first()->valor);
    }

    public function test_filtro_por_aluno_nao_lista_aula_especifica_de_outro_aluno_da_turma(): void
    {
        $ids = $this->naInstituicao($this->instituicao, function (): array {
            $aluno = Aluno::create(['id_usuario' => User::factory()->create(['role' => 'aluno'])->id]);
            $colega = Aluno::create(['id_usuario' => User::factory()->create(['role' => 'aluno'])->id]);

            foreach ([$aluno, $colega] as $matriculado) {
                Matricula::create([
                    'id_aluno' => $matriculado->id,
                    'id_turma' => $this->turma->id,
                    'tipo' => 'turma',
                    'data' => now()->toDateString(),
                    'status' => 'ativa',
                ]);
            }

            $base = [
                'id_professor' => $this->professor->id,
                'data' => '2026-03-02',
                'hora_inicio' => '14:00',
                'hora_termino' => '15:00',
                'status' => 'agendada',
            ];

            return [
                'aluno' => $aluno->id,
                'turmaInteira' => AulaTurma::create($base + ['id_turma' => $this->turma->id, 'tipo' => 'regular'])->id,
                'doAluno' => AulaTurma::create($base + ['id_turma' => $this->turma->id, 'tipo' => 'reposicao', 'id_aluno_especifico' => $aluno->id])->id,
                'avulsaDoAluno' => AulaTurma::create($base + ['id_curso' => $this->curso->id, 'tipo' => 'reforco', 'id_aluno_especifico' => $aluno->id])->id,
                'doColega' => AulaTurma::create($base + ['id_turma' => $this->turma->id, 'tipo' => 'reposicao', 'id_aluno_especifico' => $colega->id])->id,
            ];
        });

        $retornadas = collect(
            $this->comoUsuario($this->admin, $this->instituicao)
                ->getJson('/api/aulas-turmas?id_aluno=' . $ids['aluno'])
                ->assertOk()
                ->json('data')
        )->pluck('id')->sort()->values()->all();

        $esperadas = collect([$ids['turmaInteira'], $ids['doAluno'], $ids['avulsaDoAluno']])->sort()->values()->all();

        $this->assertSame($esperadas, $retornadas);
    }

    public function test_nao_cria_aula_em_turma_concluida(): void
    {
        $this->turma->update(['status' => TurmaStatus::CONCLUIDA]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/aulas-turmas', [
                'id_turma' => $this->turma->id,
                'id_professor' => $this->professor->id,
                'data' => '2026-03-02',
                'hora_inicio' => '14:00',
                'hora_termino' => '15:00',
            ])
            ->assertForbidden();

        $this->assertSame([], $this->datasDasAulas());
    }
}
