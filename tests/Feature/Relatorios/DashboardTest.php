<?php

namespace Tests\Feature\Relatorios;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\AulaPresenca;
use App\Models\AulaTurma;
use App\Models\CategoriaConta;
use App\Models\Conta;
use App\Models\ContaPagamento;
use App\Models\Curso;
use App\Models\Lead;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\TurmaHorario;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Instituicao $instituicao;

    private CategoriaConta $categoria;

    private Turma $turma;

    private Professor $professor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = Instituicao::where('slug', 'default')->first();
        $this->admin = User::factory()->create(['role' => 'admin']);

        InstituicaoUsuario::create([
            'id_instituicao' => $this->instituicao->id,
            'id_usuario' => $this->admin->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $this->categoria = CategoriaConta::create([
            'nome' => 'Mensalidade',
            'tipo' => 'receita',
        ]);

        $curso = Curso::create(['nome' => 'Piano']);
        $nivel = Nivel::create(['nome' => 'Iniciante']);
        $this->professor = Professor::factory()->create();

        $this->turma = Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $this->professor->id,
            'maximo_alunos' => 10,
            'descricao' => 'Piano Iniciante A',
            'status' => TurmaStatus::EM_ANDAMENTO,
        ]);
    }

    public function test_professor_cannot_access_dashboard(): void
    {
        $professor = User::factory()->create(['role' => 'professor']);

        InstituicaoUsuario::create([
            'id_instituicao' => $this->instituicao->id,
            'id_usuario' => $professor->id,
            'role' => 'professor',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        $token = $professor->createToken('test');
        $token->accessToken->update(['id_instituicao' => $this->instituicao->id]);

        $this->withToken($token->plainTextToken)
            ->withHeader('X-Tenant-Slug', 'default')
            ->getJson('/api/dashboard/resumo')
            ->assertStatus(403);
    }

    public function test_resumo_excludes_matriculado_leads_from_ativos(): void
    {
        Lead::create(['nome' => 'Ativo', 'status' => 'novo']);
        Lead::create(['nome' => 'Contatado', 'status' => 'contatado']);
        Lead::create(['nome' => 'Já aluno', 'status' => 'matriculado']);
        Lead::create(['nome' => 'Perdido', 'status' => 'perdido']);

        $response = $this->asAdmin()->getJson('/api/dashboard/resumo');

        $response->assertOk()
            ->assertJsonPath('leadsAtivos', 2);

        $leadsStat = collect($response->json('stats'))->firstWhere('title', 'Leads Ativos');
        $this->assertSame('2', $leadsStat['value']);
    }

    public function test_financeiro_uses_saldo_and_includes_pago_parcialmente(): void
    {
        $aluno = $this->makeAluno('Maria Silva');

        $conta = Conta::create([
            'id_categoria' => $this->categoria->id,
            'descricao' => 'Mensalidade parcial',
            'valor' => 100,
            'data_vencimento' => now()->subDays(5)->toDateString(),
            'status' => 'pago_parcialmente',
            'tipo' => 'receita',
            'id_aluno' => $aluno->id,
        ]);

        ContaPagamento::create([
            'id_conta' => $conta->id,
            'valor_pago' => 40,
            'data_pagamento' => now()->subDays(2)->toDateString(),
            'forma_pagamento' => 'pix',
        ]);

        $resumo = $this->asAdmin()->getJson('/api/dashboard/resumo');
        $inadimplencia = collect($resumo->json('stats'))->firstWhere('title', 'Inadimplência');
        $this->assertStringContainsString('60', $inadimplencia['value']);

        $financeiro = $this->asAdmin()->getJson('/api/dashboard/financeiro');
        $financeiro->assertOk();

        $item = collect($financeiro->json('inadimplenciaItens'))->firstWhere('id', $conta->id);
        $this->assertNotNull($item);
        $this->assertSame('Maria Silva', $item['name']);
        $this->assertStringContainsString('60', $item['amount']);
    }

    public function test_conversao_counts_only_matriculas_with_lead(): void
    {
        $leadA = Lead::create(['nome' => 'Lead A', 'status' => 'novo']);
        Lead::create(['nome' => 'Lead B', 'status' => 'novo']);

        $aluno = $this->makeAluno('Convertido');

        Matricula::create([
            'id_aluno' => $aluno->id,
            'id_turma' => $this->turma->id,
            'id_lead' => $leadA->id,
            'data' => now()->toDateString(),
            'status' => 'ativa',
            'tipo' => 'turma',
        ]);

        $alunoSemLead = $this->makeAluno('Sem Lead');
        Matricula::create([
            'id_aluno' => $alunoSemLead->id,
            'id_turma' => $this->turma->id,
            'data' => now()->toDateString(),
            'status' => 'ativa',
            'tipo' => 'turma',
        ]);

        $response = $this->asAdmin()->getJson('/api/dashboard/operacional');

        $response->assertOk()
            ->assertJsonPath('conversaoData.leads', 2)
            ->assertJsonPath('conversaoData.matriculas', 1)
            ->assertJsonPath('conversaoData.rate', 50);
    }

    public function test_ausentes_uses_db_status_ausente_not_falta(): void
    {
        $ausente = $this->makeAluno('Faltoso');
        $misto = $this->makeAluno('Misto');

        $aula1 = $this->makeAula(now()->subDays(4)->toDateString(), '10:00');
        $aula2 = $this->makeAula(now()->subDays(2)->toDateString(), '10:00');
        $aula3 = $this->makeAula(now()->subDays(1)->toDateString(), '10:00');

        AulaPresenca::create(['id_aula_turma' => $aula1->id, 'id_aluno' => $ausente->id, 'status' => 'ausente']);
        AulaPresenca::create(['id_aula_turma' => $aula2->id, 'id_aluno' => $ausente->id, 'status' => 'ausente']);

        AulaPresenca::create(['id_aula_turma' => $aula2->id, 'id_aluno' => $misto->id, 'status' => 'ausente']);
        AulaPresenca::create(['id_aula_turma' => $aula3->id, 'id_aluno' => $misto->id, 'status' => 'presente']);

        $response = $this->asAdmin()->getJson('/api/dashboard/operacional');

        $response->assertOk();
        $ids = collect($response->json('alunosAusentes'))->pluck('id')->all();

        $this->assertContains($ausente->id, $ids);
        $this->assertNotContains($misto->id, $ids);
    }

    public function test_ausencias_do_not_n_plus_one(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $aluno = $this->makeAluno("Aluno {$i}");
            $aula1 = $this->makeAula(now()->subDays(3)->toDateString(), sprintf('1%d:00', $i));
            $aula2 = $this->makeAula(now()->subDays(1)->toDateString(), sprintf('1%d:00', $i));
            AulaPresenca::create(['id_aula_turma' => $aula1->id, 'id_aluno' => $aluno->id, 'status' => 'ausente']);
            AulaPresenca::create(['id_aula_turma' => $aula2->id, 'id_aluno' => $aluno->id, 'status' => 'ausente']);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->asAdmin()->getJson('/api/dashboard/operacional')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(25, $count, "Operacional disparou {$count} queries");
    }

    public function test_agenda_payload_includes_turma_and_end_time(): void
    {
        $this->makeAula(now()->toDateString(), '14:00', '15:00', 'agendada');

        $response = $this->asAdmin()->getJson('/api/dashboard/operacional');

        $response->assertOk()
            ->assertJsonPath('agendaHoje.0.time', '14:00')
            ->assertJsonPath('agendaHoje.0.horaTermino', '15:00')
            ->assertJsonPath('agendaHoje.0.status', 'agendada')
            ->assertJsonPath('agendaHoje.0.course', 'Piano')
            ->assertJsonPath('agendaHoje.0.turma', 'Piano Iniciante A')
            ->assertJsonPath('agendaHoje.0.teacher', $this->professor->usuario->nome)
            ->assertJsonPath('agendaHoje.0.tipo', 'regular')
            ->assertJsonPath('agendaHoje.0.turmaId', $this->turma->id);
    }

    public function test_ocupacao_is_returned(): void
    {
        TurmaHorario::create([
            'id_turma' => $this->turma->id,
            'dia_semana' => 'segunda',
            'hora_inicio' => '14:00',
            'hora_termino' => '15:00',
        ]);

        $aluno = $this->makeAluno('Ocupado');
        Matricula::create([
            'id_aluno' => $aluno->id,
            'id_turma' => $this->turma->id,
            'data' => now()->subMonth()->toDateString(),
            'status' => 'ativa',
            'tipo' => 'turma',
        ]);

        $response = $this->asAdmin()->getJson('/api/dashboard/operacional');

        $response->assertOk()
            ->assertJsonStructure([
                'ocupacaoHorarios' => ['dias', 'horas', 'cells'],
            ]);

        $cell = collect($response->json('ocupacaoHorarios.cells'))
            ->first(fn ($c) => $c['dia'] === 'segunda' && $c['hora'] === '14:00');

        $this->assertNotNull($cell);
        $this->assertSame(1, $cell['alunos']);
        $this->assertSame(10, $cell['capacidade']);
        $this->assertEquals(10, $cell['percentual']);
    }

    private function asAdmin(): static
    {
        $token = $this->admin->createToken('test');
        $token->accessToken->update(['id_instituicao' => $this->instituicao->id]);

        return $this->withToken($token->plainTextToken)
            ->withHeader('X-Tenant-Slug', 'default');
    }

    private function makeAluno(string $nome): Aluno
    {
        $user = User::factory()->create([
            'nome' => $nome,
            'role' => 'aluno',
            'data_aniversario' => now()->toDateString(),
        ]);

        return Aluno::create([
            'id_usuario' => $user->id,
            'observacoes' => 'Teste',
        ]);
    }

    private function makeAula(string $data, string $inicio, string $termino = '11:00', string $status = 'concluida'): AulaTurma
    {
        return AulaTurma::create([
            'id_turma' => $this->turma->id,
            'id_professor' => $this->professor->id,
            'data' => $data,
            'hora_inicio' => $inicio,
            'hora_termino' => $termino,
            'status' => $status,
        ]);
    }
}
