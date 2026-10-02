<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\AvaliacaoAluno;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class ModulosEscolaTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $admin;

    private User $secretaria;

    private User $usuarioProfessor;

    private Turma $turma;

    private Aluno $aluno;

    private Matricula $matricula;

    private Nivel $intermediario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-30 15:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
        $this->secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');

        $this->naInstituicao($this->instituicao, function () use ($usuarioAluno): void {
            $curso = Curso::create(['nome' => 'Piano', 'descricao' => 'Teclas']);
            $basico = Nivel::create(['nome' => 'Básico', 'curso_id' => $curso->id, 'ordem' => 1]);
            $this->intermediario = Nivel::create(['nome' => 'Intermediário', 'curso_id' => $curso->id, 'ordem' => 2]);
            $professor = Professor::create(['id_usuario' => $this->usuarioProfessor->id]);

            $this->turma = Turma::create([
                'id_curso' => $curso->id,
                'id_nivel' => $basico->id,
                'id_professor' => $professor->id,
                'maximo_alunos' => 10,
                'status' => TurmaStatus::EM_ANDAMENTO,
                'tipo_agendamento' => 'quantidade',
                'quantidade_aulas' => 10,
                'data_inicio' => '2026-09-01',
                'valor_mensalidade' => 300,
            ]);

            $this->aluno = Aluno::create(['id_usuario' => $usuarioAluno->id]);
            $this->matricula = Matricula::create([
                'id_aluno' => $this->aluno->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'turma',
                'data' => '2026-09-01',
                'status' => 'ativa',
            ]);

            AvaliacaoAluno::create([
                'id_aluno' => $this->aluno->id,
                'id_turma' => $this->turma->id,
                'id_professor' => $professor->id,
                'data' => '2026-09-20',
                'tipo' => 'pratica',
                'nota' => 8,
            ]);

            SugestaoProgressao::create([
                'id_matricula' => $this->matricula->id,
                'id_nivel_atual' => $basico->id,
                'id_nivel_sugerido' => $this->intermediario->id,
                'id_professor' => $professor->id,
                'status' => SugestaoProgressao::STATUS_PENDENTE,
                'justificativa' => 'Pronto para avançar.',
            ]);
        });
    }

    private function alterar(string $modulo, bool $ativo)
    {
        return $this->comoUsuario($this->admin, $this->instituicao)
            ->putJson("/api/instituicao/modulos/{$modulo}", ['ativo' => $ativo]);
    }

    public function test_admin_lista_modulos_com_a_situacao_na_escola(): void
    {
        $resposta = $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson('/api/instituicao/modulos')
            ->assertOk();

        $porChave = collect($resposta->json('data'))->keyBy('key');

        $this->assertSame(
            ['key' => 'progressao', 'label' => 'Progressão de nível', 'descricao' => $porChave['progressao']['descricao'], 'contratado' => true, 'ativo' => true, 'desativavel' => true],
            $porChave['progressao']
        );
        $this->assertTrue($porChave['avaliacoes']['desativavel']);
        $this->assertFalse($porChave['financeiro']['desativavel']);
        $this->assertContains('avaliacoes', $resposta->json('meta.modulos_ativos'));
    }

    public function test_somente_admin_gerencia_modulos(): void
    {
        $this->getJson('/api/instituicao/modulos', ['X-Tenant-Slug' => $this->instituicao->slug])->assertUnauthorized();

        foreach ([$this->secretaria, $this->usuarioProfessor] as $usuario) {
            $this->comoUsuario($usuario, $this->instituicao)->getJson('/api/instituicao/modulos')->assertForbidden();
            $this->comoUsuario($usuario, $this->instituicao)
                ->putJson('/api/instituicao/modulos/progressao', ['ativo' => false])
                ->assertForbidden();
        }

        $this->assertNull($this->instituicao->fresh()->modulos_desativados);
    }

    public function test_escola_em_trial_desliga_e_religa_modulo(): void
    {
        $this->alterar('progressao', false)
            ->assertOk()
            ->assertJsonPath('meta.modulos_ativos', fn (array $ativos) => ! in_array('progressao', $ativos, true) && in_array('avaliacoes', $ativos, true));

        $this->assertSame(['progressao'], $this->instituicao->fresh()->modulos_desativados);

        $tenant = collect(
            $this->comoUsuario($this->admin, $this->instituicao)->getJson('/api/instituicoes/mine')->assertOk()->json('data')
        )->firstWhere('id', $this->instituicao->id);

        $this->assertTrue($tenant['em_trial']);
        $this->assertNotContains('progressao', $tenant['modulos']);
        $this->assertContains('progressao', $tenant['modulos_contratados']);

        $this->alterar('progressao', true)
            ->assertOk()
            ->assertJsonPath('meta.modulos_ativos', fn (array $ativos) => in_array('progressao', $ativos, true));

        $this->assertNull($this->instituicao->fresh()->modulos_desativados);
    }

    public function test_nao_desliga_modulo_que_nao_e_opcional(): void
    {
        $this->alterar('financeiro', false)->assertUnprocessable()->assertJsonValidationErrors(['modulo']);
        $this->alterar('inexistente', false)->assertUnprocessable()->assertJsonValidationErrors(['modulo']);
        $this->alterar('avaliacoes', true)->assertOk();
        $this->comoUsuario($this->admin, $this->instituicao)
            ->putJson('/api/instituicao/modulos/avaliacoes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ativo']);
    }

    public function test_modulo_desligado_bloqueia_rotas_oculta_da_ficha_e_preserva_os_dados(): void
    {
        $this->alterar('avaliacoes', false)->assertOk();
        $this->alterar('progressao', false)->assertOk();

        $comoProfessor = fn () => $this->comoUsuario($this->usuarioProfessor, $this->instituicao);

        $comoProfessor()->getJson("/api/professor/me/avaliacoes?id_turma={$this->turma->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'modulo_desativado')
            ->assertJsonPath('modulo', 'avaliacoes');
        $comoProfessor()->getJson('/api/professor/me/niveis')->assertForbidden()->assertJsonPath('code', 'modulo_desativado');
        $comoProfessor()->postJson('/api/professor/me/progressoes', [
            'id_matricula' => $this->matricula->id,
            'id_nivel_sugerido' => $this->intermediario->id,
            'justificativa' => 'Teste',
        ])->assertForbidden()->assertJsonPath('code', 'modulo_desativado');
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->getJson('/api/progressoes')
            ->assertForbidden()
            ->assertJsonPath('code', 'modulo_desativado');

        $comoProfessor()->getJson("/api/professor/me/alunos/{$this->aluno->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.avaliacoes')
            ->assertJsonCount(0, 'data.progressoes');

        $this->alterar('avaliacoes', true)->assertOk();
        $this->alterar('progressao', true)->assertOk();

        $comoProfessor()->getJson("/api/professor/me/avaliacoes?id_turma={$this->turma->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->getJson('/api/progressoes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $comoProfessor()->getJson("/api/professor/me/alunos/{$this->aluno->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.avaliacoes')
            ->assertJsonCount(1, 'data.progressoes');
    }

    public function test_plano_sem_o_modulo_bloqueia_e_a_escola_nao_consegue_liga_lo(): void
    {
        $plano = PlanoAssinatura::query()->create([
            'slug' => 'so-leads',
            'nome' => 'Só Leads',
            'preco_mensal' => 50,
            'limite_alunos' => 50,
            'modulos' => ['leads', 'avaliacoes'],
            'ativo' => true,
        ]);
        $this->instituicao->update([
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            'id_plano_assinatura' => $plano->id,
        ]);

        $this->alterar('progressao', true)->assertUnprocessable()->assertJsonValidationErrors(['modulo']);

        $this->comoUsuario($this->secretaria, $this->instituicao)
            ->getJson('/api/progressoes')
            ->assertForbidden()
            ->assertJsonPath('code', 'modulo_nao_incluido');

        $this->comoUsuario($this->usuarioProfessor, $this->instituicao)
            ->getJson("/api/professor/me/avaliacoes?id_turma={$this->turma->id}")
            ->assertOk();

        $porChave = collect(
            $this->comoUsuario($this->admin, $this->instituicao)->getJson('/api/instituicao/modulos')->json('data')
        )->keyBy('key');

        $this->assertFalse($porChave['progressao']['contratado']);
        $this->assertFalse($porChave['progressao']['ativo']);
        $this->assertTrue($porChave['avaliacoes']['ativo']);
    }
}
