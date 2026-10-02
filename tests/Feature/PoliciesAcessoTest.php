<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\Responsavel;
use App\Models\User;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Pessoas\Models\MedidaAluno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class PoliciesAcessoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAluno;

    private User $usuarioResponsavel;

    private User $usuarioOutroResponsavel;

    private User $usuarioProfessor;

    private User $usuarioSecretaria;

    private Aluno $aluno;

    private Aluno $outroAluno;

    private Responsavel $responsavel;

    private Turma $turma;

    private Turma $outraTurma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();

        $this->usuarioAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $usuarioOutroAluno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $this->usuarioResponsavel = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel');
        $this->usuarioOutroResponsavel = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel');
        $this->usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $this->usuarioSecretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $usuarioOutroProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');

        $this->naInstituicao($this->instituicao, function () use ($usuarioOutroAluno, $usuarioOutroProfessor): void {
            $curso = Curso::create(['nome' => 'Piano', 'descricao' => 'Teclas']);
            $nivel = Nivel::create(['nome' => 'Básico']);

            $professor = Professor::create(['id_usuario' => $this->usuarioProfessor->id, 'comissao' => 10]);
            $outroProfessor = Professor::create(['id_usuario' => $usuarioOutroProfessor->id, 'comissao' => 10]);

            $this->turma = $this->criarTurma($curso, $nivel, $professor);
            $this->outraTurma = $this->criarTurma($curso, $nivel, $outroProfessor);

            $this->aluno = Aluno::create(['id_usuario' => $this->usuarioAluno->id]);
            $this->outroAluno = Aluno::create(['id_usuario' => $usuarioOutroAluno->id]);

            $this->responsavel = Responsavel::create(['id_usuario' => $this->usuarioResponsavel->id]);
            Responsavel::create(['id_usuario' => $this->usuarioOutroResponsavel->id]);
            $this->aluno->responsaveis()->attach($this->responsavel->id, ['parentesco' => 'Pai']);

            Matricula::create([
                'id_aluno' => $this->aluno->id,
                'id_turma' => $this->turma->id,
                'tipo' => 'turma',
                'data' => now()->toDateString(),
                'status' => 'ativa',
            ]);
        });
    }

    private function criarTurma(Curso $curso, Nivel $nivel, Professor $professor): Turma
    {
        return Turma::create([
            'id_curso' => $curso->id,
            'id_nivel' => $nivel->id,
            'id_professor' => $professor->id,
            'maximo_alunos' => 10,
            'status' => TurmaStatus::ABERTA,
            'tipo_agendamento' => 'quantidade',
            'quantidade_aulas' => 10,
            'data_inicio' => now()->toDateString(),
        ]);
    }

    private function pode(User $user, string $ability, mixed $argumentos): bool
    {
        return $this->naInstituicao(
            $this->instituicao,
            fn () => Gate::forUser($user->fresh())->allows($ability, $argumentos)
        );
    }

    private function criarConta(Aluno $aluno): Conta
    {
        return $this->naInstituicao($this->instituicao, function () use ($aluno): Conta {
            $categoria = CategoriaConta::create(['nome' => 'Mensalidades', 'tipo' => 'receita']);

            return Conta::create([
                'id_categoria' => $categoria->id,
                'id_aluno' => $aluno->id,
                'descricao' => 'Mensalidade',
                'valor' => 100,
                'data_vencimento' => now()->toDateString(),
                'status' => 'pendente',
                'tipo' => 'receita',
            ]);
        });
    }

    public function test_conta_respeita_aluno_responsavel_e_libera_outros_papeis(): void
    {
        $conta = $this->criarConta($this->aluno);
        $contaAlheia = $this->criarConta($this->outroAluno);
        $admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');

        $this->assertTrue($this->pode($this->usuarioAluno, 'view', $conta));
        $this->assertFalse($this->pode($this->usuarioAluno, 'view', $contaAlheia));
        $this->assertTrue($this->pode($this->usuarioResponsavel, 'pagar', $conta));
        $this->assertFalse($this->pode($this->usuarioResponsavel, 'pagar', $contaAlheia));
        $this->assertTrue($this->pode($this->usuarioSecretaria, 'view', $contaAlheia));
        $this->assertTrue($this->pode($admin, 'view', $contaAlheia));
    }

    public function test_responsavel_nao_ve_conta_alheia_via_api_com_mensagem_original(): void
    {
        $contaAlheia = $this->criarConta($this->outroAluno);

        $this->comoUsuario($this->usuarioResponsavel, $this->instituicao)
            ->getJson("/api/contas/{$contaAlheia->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Acesso Restrito: Você não é responsável pelo aluno vinculado a esta conta.');
    }

    public function test_turma_visivel_para_aluno_matriculado_e_professor_da_turma(): void
    {
        $this->assertTrue($this->pode($this->usuarioAluno, 'view', $this->turma));
        $this->assertFalse($this->pode($this->usuarioAluno, 'view', $this->outraTurma));
        $this->assertTrue($this->pode($this->usuarioProfessor, 'gerenciarAulas', $this->turma));
        $this->assertFalse($this->pode($this->usuarioProfessor, 'gerenciarAulas', $this->outraTurma));
        $this->assertTrue($this->pode($this->usuarioSecretaria, 'gerenciarAulas', $this->outraTurma));
    }

    public function test_escopo_de_turmas_do_responsavel_lista_turmas_dos_dependentes(): void
    {
        $ids = $this->naInstituicao(
            $this->instituicao,
            fn () => Turma::visivelPara($this->usuarioResponsavel->fresh())->pluck('id')->all()
        );

        $this->assertSame([$this->turma->id], $ids);
    }

    public function test_presencas_e_materiais_restritos_ao_professor_da_turma(): void
    {
        [$aula, $material] = $this->naInstituicao($this->instituicao, fn () => [
            AulaTurma::create([
                'id_turma' => $this->outraTurma->id,
                'id_professor' => $this->outraTurma->id_professor,
                'data' => now()->toDateString(),
                'hora_inicio' => '10:00',
                'hora_termino' => '11:00',
                'status' => 'agendada',
            ]),
            MaterialTurma::create([
                'id_turma' => $this->outraTurma->id,
                'titulo' => 'Partitura',
                'file_path' => 'x.pdf',
            ]),
        ]);

        $this->assertFalse($this->pode($this->usuarioProfessor, 'registrarPresencas', $aula));
        $this->assertFalse($this->pode($this->usuarioProfessor, 'delete', $material));
        $this->assertTrue($this->pode($this->usuarioSecretaria, 'registrarPresencas', $aula));
        $this->assertTrue($this->pode($this->usuarioSecretaria, 'delete', $material));
    }

    public function test_medidas_do_aluno(): void
    {
        $medida = $this->naInstituicao(
            $this->instituicao,
            fn () => MedidaAluno::create(['id_aluno' => $this->aluno->id, 'medida_altura' => 150])
        );

        $this->assertTrue($this->pode($this->usuarioResponsavel, 'view', $medida));
        $this->assertFalse($this->pode($this->usuarioOutroResponsavel, 'view', $medida));
        $this->assertTrue($this->pode($this->usuarioAluno, 'viewAnyDoAluno', [MedidaAluno::class, $this->aluno->id]));
        $this->assertFalse($this->pode($this->usuarioAluno, 'viewAnyDoAluno', [MedidaAluno::class, $this->outroAluno->id]));
    }

    public function test_responsavel_so_ve_e_altera_o_proprio_perfil(): void
    {
        $this->assertTrue($this->pode($this->usuarioResponsavel, 'view', $this->responsavel));
        $this->assertFalse($this->pode($this->usuarioOutroResponsavel, 'update', $this->responsavel));
        $this->assertTrue($this->pode($this->usuarioSecretaria, 'update', $this->responsavel));
    }
}
