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
use App\Modules\Core\Models\Instituicao;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class MensalidadesMatriculaTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $admin;

    private Aluno $aluno;

    private Turma $turma;

    private Turma $turmaDestino;

    private CategoriaConta $categoria;

    private Curso $curso;

    private Nivel $nivel;

    private Professor $professor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');

        $this->naInstituicao($this->instituicao, function () use ($usuarioAluno, $usuarioProfessor): void {
            $this->categoria = CategoriaConta::firstOrCreate(['nome' => 'Mensalidades'], ['tipo' => 'receita']);
            $this->curso = Curso::create(['nome' => 'Ballet', 'descricao' => 'Dança']);
            $this->nivel = Nivel::create(['nome' => 'Iniciante']);
            $nivelAvancado = Nivel::create(['nome' => 'Avançado']);
            $this->professor = Professor::create(['id_usuario' => $usuarioProfessor->id, 'comissao' => 10]);

            $this->turma = Turma::create([
                'id_curso' => $this->curso->id,
                'id_nivel' => $this->nivel->id,
                'id_professor' => $this->professor->id,
                'maximo_alunos' => 20,
                'status' => TurmaStatus::ABERTA,
                'tipo_agendamento' => 'quantidade',
                'quantidade_aulas' => 12,
                'data_inicio' => '2026-03-01',
                'valor_mensalidade' => 150,
                'modo_geracao_mensalidades' => 'automatico',
                'dia_vencimento_mensalidade' => 10,
                'quantidade_parcelas_mensalidade' => 3,
            ]);

            $this->turmaDestino = Turma::create([
                'id_curso' => $this->curso->id,
                'id_nivel' => $nivelAvancado->id,
                'id_professor' => $this->professor->id,
                'maximo_alunos' => 20,
                'status' => TurmaStatus::ABERTA,
                'tipo_agendamento' => 'quantidade',
                'quantidade_aulas' => 12,
                'data_inicio' => '2026-03-01',
                'valor_mensalidade' => 200,
                'dia_vencimento_mensalidade' => 15,
                'quantidade_parcelas_mensalidade' => 2,
            ]);

            $this->aluno = Aluno::create(['id_usuario' => $usuarioAluno->id]);
        });
    }

    public function test_modo_automatico_gera_mensalidades_ao_criar_matricula(): void
    {
        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => '2026-03-01',
                'status' => 'ativa',
            ])
            ->assertCreated();

        $matriculaId = $response->json('id');

        $this->assertSame(3, Conta::query()->where('id_matricula', $matriculaId)->count());
        $this->assertEquals(150.0, (float) Conta::query()->where('id_matricula', $matriculaId)->first()->valor);
    }

    public function test_modo_formulario_sem_flag_nao_gera(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->turma->update(['modo_geracao_mensalidades' => 'formulario']);
        });

        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => '2026-03-01',
                'status' => 'ativa',
            ])
            ->assertCreated();

        $this->assertSame(0, Conta::query()->where('id_matricula', $response->json('id'))->count());
    }

    public function test_modo_formulario_com_flag_gera(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->turma->update(['modo_geracao_mensalidades' => 'formulario']);
        });

        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => '2026-03-01',
                'status' => 'ativa',
                'gerar_mensalidades' => true,
                'valor_mensalidade_geracao' => 180,
                'quantidade_parcelas' => 2,
                'dia_vencimento' => 5,
                'data_inicio_mensalidades' => '2026-04-01',
            ])
            ->assertCreated();

        $contas = Conta::query()->where('id_matricula', $response->json('id'))->orderBy('mes_referencia')->get();
        $this->assertCount(2, $contas);
        $this->assertEquals(180.0, (float) $contas->first()->valor);
        $this->assertSame(4, (int) $contas->first()->mes_referencia);
    }

    public function test_geracao_e_idempotente_por_mes_referencia(): void
    {
        $matricula = $this->naInstituicao($this->instituicao, function (): Matricula {
            return Matricula::create([
                'id_aluno' => $this->aluno->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'turma',
                'data' => '2026-03-01',
                'status' => 'ativa',
            ]);
        });

        $payload = [
            'valor' => 150,
            'quantidade_parcelas' => 3,
            'dia_vencimento' => 10,
            'data_inicio' => '2026-03-01',
            'id_categoria' => $this->categoria->id,
        ];

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/matriculas/{$matricula->id}/gerar-mensalidades", $payload)
            ->assertCreated()
            ->assertJsonPath('count', 3);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson("/api/matriculas/{$matricula->id}/gerar-mensalidades", $payload)
            ->assertCreated()
            ->assertJsonPath('count', 0);

        $this->assertSame(3, Conta::query()->where('id_matricula', $matricula->id)->count());
    }

    public function test_transferencia_reajusta_e_gera_novas_independente(): void
    {
        $matricula = $this->naInstituicao($this->instituicao, function (): Matricula {
            $matricula = Matricula::create([
                'id_aluno' => $this->aluno->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'turma',
                'data' => '2026-03-01',
                'status' => 'ativa',
            ]);

            Conta::create([
                'id_categoria' => $this->categoria->id,
                'descricao' => 'Mensalidade restante',
                'valor' => 150,
                'data_vencimento' => now()->addMonth()->day(10)->toDateString(),
                'status' => 'pendente',
                'tipo' => 'receita',
                'id_aluno' => $this->aluno->id,
                'id_matricula' => $matricula->id,
                'mes_referencia' => (int) now()->addMonth()->format('n'),
                'ano_referencia' => (int) now()->addMonth()->format('Y'),
                'numero_parcela' => 1,
                'quantidade_parcelas' => 1,
            ]);

            Conta::create([
                'id_categoria' => $this->categoria->id,
                'descricao' => 'Mensalidade antiga',
                'valor' => 150,
                'data_vencimento' => now()->subMonths(2)->day(10)->toDateString(),
                'status' => 'pendente',
                'tipo' => 'receita',
                'id_aluno' => $this->aluno->id,
                'id_matricula' => $matricula->id,
                'mes_referencia' => (int) now()->subMonths(2)->format('n'),
                'ano_referencia' => (int) now()->subMonths(2)->format('Y'),
                'numero_parcela' => 1,
                'quantidade_parcelas' => 1,
            ]);

            return $matricula;
        });

        $mesRestante = (int) now()->addMonth()->format('n');
        $anoRestante = (int) now()->addMonth()->format('Y');
        $mesAntiga = (int) now()->subMonths(2)->format('n');
        $anoAntiga = (int) now()->subMonths(2)->format('Y');
        $inicioLote = now()->addMonths(2)->startOfMonth()->toDateString();
        $mesesLote = [
            (int) now()->addMonths(2)->format('n'),
            (int) now()->addMonths(3)->format('n'),
        ];

        $this->comoUsuario($this->admin, $this->instituicao)
            ->putJson("/api/matriculas/{$matricula->id}", [
                'id_turma' => $this->turmaDestino->id,
                'motivo_transferencia' => 'Progressão de nível',
                'reajustar_mensalidades' => true,
                'gerar_novas_mensalidades' => true,
                'valor_mensalidade_geracao' => 200,
                'quantidade_parcelas' => 2,
                'dia_vencimento' => 15,
                'data_inicio_mensalidades' => $inicioLote,
            ])
            ->assertOk();

        $restante = Conta::query()
            ->where('id_matricula', $matricula->id)
            ->where('mes_referencia', $mesRestante)
            ->where('ano_referencia', $anoRestante)
            ->first();
        $antiga = Conta::query()
            ->where('id_matricula', $matricula->id)
            ->where('mes_referencia', $mesAntiga)
            ->where('ano_referencia', $anoAntiga)
            ->first();

        $this->assertEquals(200.0, (float) $restante->valor);
        $this->assertEquals(150.0, (float) $antiga->valor);
        $this->assertSame(2, Conta::query()
            ->where('id_matricula', $matricula->id)
            ->whereIn('mes_referencia', $mesesLote)
            ->count());
    }

    public function test_turma_override_prevalece_sobre_instituicao(): void
    {
        $this->instituicao->update([
            'modo_geracao_mensalidades' => 'formulario',
            'quantidade_parcelas_mensalidade' => 6,
        ]);

        $this->naInstituicao($this->instituicao, function (): void {
            $this->turma->update([
                'modo_geracao_mensalidades' => 'automatico',
                'quantidade_parcelas_mensalidade' => 2,
            ]);
        });

        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => '2026-03-01',
                'status' => 'ativa',
            ])
            ->assertCreated();

        $this->assertSame(2, Conta::query()->where('id_matricula', $response->json('id'))->count());
    }

    public function test_configuracao_empresa_aceita_campos_de_mensalidade(): void
    {
        $this->instituicao->update([
            'nome_fantasia' => 'Escola Teste Mensalidade',
            'cnpj' => '11222333000181',
        ]);

        $this->comoUsuario($this->admin, $this->instituicao)
            ->putJson('/api/configuracao-empresa', [
                'nome_fantasia' => 'Escola Teste Mensalidade',
                'razao_social' => 'Escola Teste LTDA',
                'cnpj' => '11222333000181',
                'email_contato' => 'contato@escola-mensalidade.test',
                'whatsapp' => '11977776666',
                'rua' => 'Rua A',
                'numero' => '100',
                'bairro' => 'Centro',
                'complemento' => null,
                'cep' => '01001000',
                'id_estado' => $this->instituicao->id_estado,
                'id_cidade' => $this->instituicao->id_cidade,
                'frequencia_minima' => 75,
                'modo_geracao_mensalidades' => 'duracao_turma',
                'dia_vencimento_mensalidade' => 8,
                'quantidade_parcelas_mensalidade' => 10,
                'inicio_parcelas_mensalidade' => 'mes_seguinte',
            ])
            ->assertOk()
            ->assertJsonPath('modo_geracao_mensalidades', 'duracao_turma')
            ->assertJsonPath('dia_vencimento_mensalidade', 8)
            ->assertJsonPath('quantidade_parcelas_mensalidade', 10)
            ->assertJsonPath('inicio_parcelas_mensalidade', 'mes_seguinte');
    }

    public function test_duracao_turma_matricula_antes_do_inicio_usa_periodo_completo(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->turma->update([
                'modo_geracao_mensalidades' => 'duracao_turma',
                'tipo_agendamento' => 'datas',
                'data_inicio' => '2026-03-01',
                'data_fim' => '2026-08-31',
                'quantidade_parcelas_mensalidade' => 12,
            ]);
        });

        // Mar–Ago = 6 meses
        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => '2026-02-15',
                'status' => 'ativa',
            ])
            ->assertCreated();

        $this->assertSame(6, Conta::query()->where('id_matricula', $response->json('id'))->count());
    }

    public function test_duracao_turma_ignora_quantidade_parcelas_do_payload(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->turma->update([
                'modo_geracao_mensalidades' => 'duracao_turma',
                'tipo_agendamento' => 'datas',
                'data_inicio' => '2026-09-15',
                'data_fim' => '2027-03-15',
                'quantidade_parcelas_mensalidade' => 12,
            ]);
        });

        // Set–Mar = 7 meses; payload tenta forçar 12 (bug da UI)
        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => '2026-09-01',
                'status' => 'ativa',
                'gerar_mensalidades' => true,
                'quantidade_parcelas' => 12,
                'valor_mensalidade_geracao' => 250,
                'data_inicio_mensalidades' => '2026-09-01',
            ])
            ->assertCreated();

        $this->assertSame(7, Conta::query()->where('id_matricula', $response->json('id'))->count());
    }

    public function test_duracao_turma_matricula_no_meio_gera_apenas_restantes(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->turma->update([
                'modo_geracao_mensalidades' => 'duracao_turma',
                'tipo_agendamento' => 'datas',
                'data_inicio' => '2026-03-01',
                'data_fim' => '2026-08-31',
                'quantidade_parcelas_mensalidade' => 12,
            ]);
        });

        // Mai–Ago = 4 meses
        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => '2026-05-10',
                'status' => 'ativa',
            ])
            ->assertCreated();

        $this->assertSame(4, Conta::query()->where('id_matricula', $response->json('id'))->count());
    }

    public function test_duracao_turma_sem_data_fim_usa_quantidade_fixa(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->turma->update([
                'modo_geracao_mensalidades' => 'duracao_turma',
                'data_inicio' => '2026-03-01',
                'data_fim' => null,
                'quantidade_parcelas_mensalidade' => 3,
            ]);
        });

        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => '2026-03-01',
                'status' => 'ativa',
            ])
            ->assertCreated();

        $this->assertSame(3, Conta::query()->where('id_matricula', $response->json('id'))->count());
    }

    public function test_matricula_curso_com_flag_gera_mensalidades(): void
    {
        $this->instituicao->update([
            'quantidade_parcelas_mensalidade' => 12,
        ]);

        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'curso',
                'id_curso' => $this->curso->id,
                'id_nivel' => $this->nivel->id,
                'id_professor' => $this->professor->id,
                'data' => '2026-03-01',
                'status' => 'ativa',
                'gerar_mensalidades' => true,
                'valor_mensalidade_geracao' => 220,
                'quantidade_parcelas' => 5,
                'dia_vencimento' => 5,
                'data_inicio_mensalidades' => '2026-03-01',
            ])
            ->assertCreated();

        $contas = Conta::query()->where('id_matricula', $response->json('id'))->get();
        $this->assertCount(5, $contas);
        $this->assertEquals(220.0, (float) $contas->first()->valor);
    }

    public function test_matricula_curso_sem_flag_nao_gera(): void
    {
        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'curso',
                'id_curso' => $this->curso->id,
                'id_nivel' => $this->nivel->id,
                'id_professor' => $this->professor->id,
                'data' => '2026-03-01',
                'status' => 'ativa',
            ])
            ->assertCreated();

        $this->assertSame(0, Conta::query()->where('id_matricula', $response->json('id'))->count());
    }

    public function test_matricula_curso_com_valor_sem_flag_ainda_gera(): void
    {
        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'curso',
                'id_curso' => $this->curso->id,
                'id_nivel' => $this->nivel->id,
                'id_professor' => $this->professor->id,
                'data' => '2026-03-01',
                'status' => 'ativa',
                'valor_mensalidade_geracao' => 180,
                'quantidade_parcelas' => 3,
                'dia_vencimento' => 10,
                'data_inicio_mensalidades' => '2026-03-01',
            ])
            ->assertCreated();

        $this->assertSame(3, Conta::query()->where('id_matricula', $response->json('id'))->count());
    }

    public function test_matricula_curso_flag_sem_valor_retorna_erro(): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'curso',
                'id_curso' => $this->curso->id,
                'id_nivel' => $this->nivel->id,
                'id_professor' => $this->professor->id,
                'data' => '2026-03-01',
                'status' => 'ativa',
                'gerar_mensalidades' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['valor_mensalidade_geracao']);
    }

    public function test_mes_seguinte_automatico_primeira_parcela_no_mes_seguinte(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->turma->update([
                'modo_geracao_mensalidades' => 'automatico',
                'inicio_parcelas_mensalidade' => 'mes_seguinte',
                'quantidade_parcelas_mensalidade' => 3,
            ]);
        });

        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => '2026-03-15',
                'status' => 'ativa',
            ])
            ->assertCreated();

        $contas = Conta::query()
            ->where('id_matricula', $response->json('id'))
            ->orderBy('ano_referencia')
            ->orderBy('mes_referencia')
            ->get();

        $this->assertCount(3, $contas);
        $this->assertSame(4, (int) $contas->first()->mes_referencia);
        $this->assertSame(2026, (int) $contas->first()->ano_referencia);
    }

    public function test_duracao_turma_mes_seguinte_recalcula_quantidade(): void
    {
        $this->naInstituicao($this->instituicao, function (): void {
            $this->turma->update([
                'modo_geracao_mensalidades' => 'duracao_turma',
                'inicio_parcelas_mensalidade' => 'mes_seguinte',
                'tipo_agendamento' => 'datas',
                'data_inicio' => '2026-03-01',
                'data_fim' => '2026-08-31',
                'quantidade_parcelas_mensalidade' => 12,
            ]);
        });

        // Matrícula em mar → início efetivo abr → Ago = 5 meses
        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'turma',
                'id_turma' => $this->turma->id,
                'data' => '2026-03-10',
                'status' => 'ativa',
            ])
            ->assertCreated();

        $contas = Conta::query()
            ->where('id_matricula', $response->json('id'))
            ->orderBy('ano_referencia')
            ->orderBy('mes_referencia')
            ->get();

        $this->assertCount(5, $contas);
        $this->assertSame(4, (int) $contas->first()->mes_referencia);
    }

    public function test_curso_respeita_data_inicio_mensalidades_explicita(): void
    {
        $this->instituicao->update([
            'inicio_parcelas_mensalidade' => 'mes_seguinte',
        ]);

        $response = $this->comoUsuario($this->admin, $this->instituicao)
            ->postJson('/api/matriculas', [
                'id_aluno' => $this->aluno->id,
                'tipo' => 'curso',
                'id_curso' => $this->curso->id,
                'id_nivel' => $this->nivel->id,
                'id_professor' => $this->professor->id,
                'data' => '2026-03-01',
                'status' => 'ativa',
                'gerar_mensalidades' => true,
                'valor_mensalidade_geracao' => 220,
                'quantidade_parcelas' => 2,
                'dia_vencimento' => 5,
                'inicio_parcelas_mensalidade' => 'mes_seguinte',
                'data_inicio_mensalidades' => '2026-05-01',
            ])
            ->assertCreated();

        $contas = Conta::query()
            ->where('id_matricula', $response->json('id'))
            ->orderBy('ano_referencia')
            ->orderBy('mes_referencia')
            ->get();

        $this->assertCount(2, $contas);
        $this->assertSame(5, (int) $contas->first()->mes_referencia);
        $this->assertSame(2026, (int) $contas->first()->ano_referencia);
    }
}
