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
use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoResponsavelTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $carla;

    private Aluno $bruno;

    private Matricula $matriculaAna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioCarla = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Carla Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $usuarioPaula = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioCarla, $usuarioBruno, $usuarioPaula): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $paula = Professor::create(['id_usuario' => $usuarioPaula->id]);

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->carla = Aluno::create(['id_usuario' => $usuarioCarla->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id, 'observacoes' => 'segredo da responsavel']);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes([
                'parentesco' => 'mãe',
                'observacoes' => 'segredo do vinculo',
            ]));
            $this->carla->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $turmaAna = $this->turma($curso, $nivel, $paula, 'Turma Ana');
            $turmaCarla = $this->turma($curso, $nivel, $paula, 'Turma Carla');
            $turmaBruno = $this->turma($curso, $nivel, $paula, 'Turma Bruno');

            $this->matriculaAna = $this->matricular($this->ana, $turmaAna);
            $this->matricular($this->carla, $turmaCarla);
            $this->matricular($this->bruno, $turmaBruno);

            $aulaAna = $this->aula($turmaAna, $paula);
            $aulaCarla = $this->aula($turmaCarla, $paula);
            AulaPresenca::create(['id_aula_turma' => $aulaAna->id, 'id_aluno' => $this->ana->id, 'status' => 'presente']);
            AulaPresenca::create(['id_aula_turma' => $aulaCarla->id, 'id_aluno' => $this->carla->id, 'status' => 'ausente']);

            MaterialTurma::create(['id_turma' => $turmaAna->id, 'titulo' => 'Partitura da Ana', 'link' => 'https://exemplo.test/ana', 'publico' => true]);
            MaterialTurma::create(['id_turma' => $turmaCarla->id, 'titulo' => 'Partitura da Carla', 'link' => 'https://exemplo.test/carla', 'publico' => true]);
            MaterialTurma::create(['id_turma' => $turmaBruno->id, 'titulo' => 'Partitura do Bruno', 'link' => 'https://exemplo.test/bruno', 'publico' => true]);

            $categoria = CategoriaConta::firstOrCreate(['nome' => 'Mensalidades'], ['tipo' => 'receita']);
            Conta::create([
                'id_categoria' => $categoria->id,
                'id_aluno' => $this->ana->id,
                'descricao' => 'Mensalidade da Ana',
                'valor' => 100,
                'data_vencimento' => '2026-10-01',
                'status' => 'pendente',
                'tipo' => 'receita',
                'observacoes' => 'segredo da conta',
            ]);
            Conta::create([
                'id_categoria' => $categoria->id,
                'id_aluno' => $this->carla->id,
                'descricao' => 'Mensalidade da Carla',
                'valor' => 80,
                'data_vencimento' => '2026-11-10',
                'status' => 'pendente',
                'tipo' => 'receita',
            ]);
            Conta::create([
                'id_categoria' => $categoria->id,
                'id_aluno' => $this->bruno->id,
                'descricao' => 'Mensalidade do Bruno',
                'valor' => 90,
                'data_vencimento' => '2026-10-05',
                'status' => 'pendente',
                'tipo' => 'receita',
            ]);
        });
    }

    public function test_lista_so_os_dependentes_vinculados(): void
    {
        $lista = $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/dependentes')
            ->assertOk()
            ->json('data');

        $this->assertSame(['Ana Lima', 'Carla Lima'], collect($lista)->pluck('nome')->all());
        $this->assertSame('mãe', $lista[0]['parentesco']);
        $this->assertSame($this->ana->id, $lista[0]['id']);

        $corpo = json_encode($lista);
        $this->assertStringNotContainsString('Bruno Melo', $corpo);
        $this->assertStringNotContainsString('segredo do vinculo', $corpo);
        $this->assertStringNotContainsString('segredo da responsavel', $corpo);

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/dependentes')
            ->assertForbidden();
    }

    public function test_cada_dependente_aparece_so_quando_escolhido(): void
    {
        $this->comoUsuario($this->usuarioRita, $this->instituicao);

        foreach ([
            '/api/aluno/me/painel',
            '/api/aluno/me/matriculas',
            '/api/aluno/me/agenda?inicio=2026-10-26&fim=2026-11-01',
            '/api/aluno/me/frequencia',
            '/api/aluno/me/materiais',
            '/api/aluno/me/contas?situacao=em_aberto',
        ] as $url) {
            $this->getJson($url)->assertStatus(422)->assertJsonValidationErrors('id_aluno');
            $separador = str_contains($url, '?') ? '&' : '?';
            $this->getJson($url.$separador.'id_aluno='.$this->bruno->id)->assertForbidden();
        }

        $this->verComo($this->ana->id, 'Turma Ana', 'Partitura da Ana', 'Mensalidade da Ana');
        $this->verComo($this->carla->id, 'Turma Carla', 'Partitura da Carla', 'Mensalidade da Carla');

        $this->getJson('/api/aluno/me/matriculas/'.$this->matriculaAna->id.'/historico?id_aluno='.$this->ana->id)
            ->assertOk()
            ->assertJsonPath('data.0.motivo', 'Matrícula inicial');
        $this->getJson('/api/aluno/me/matriculas/'.$this->matriculaAna->id.'/historico?id_aluno='.$this->carla->id)
            ->assertForbidden();

        $this->getJson('/api/me/perfil')
            ->assertOk()
            ->assertJsonPath('data.nome', 'Rita Lima');
    }

    public function test_consolidado_junta_os_dependentes_e_exige_o_modulo(): void
    {
        $contas = $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/contas/consolidado?situacao=em_aberto')
            ->assertOk()
            ->json('data');

        $this->assertSame(['Mensalidade da Ana', 'Mensalidade da Carla'], collect($contas)->pluck('descricao')->all());
        $this->assertSame('Ana Lima', $contas[0]['aluno']['nome']);
        $this->assertSame('Carla Lima', $contas[1]['aluno']['nome']);
        $this->assertArrayNotHasKey('observacoes', $contas[0]);
        $this->assertStringNotContainsString('Mensalidade do Bruno', json_encode($contas));
        $this->assertStringNotContainsString('segredo da conta', json_encode($contas));

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/contas/consolidado')
            ->assertForbidden();

        $plano = PlanoAssinatura::create([
            'slug' => 'sem-financeiro',
            'nome' => 'Sem financeiro',
            'preco_mensal' => 10,
            'limite_alunos' => 50,
            'modulos' => ['leads'],
            'ativo' => true,
        ]);
        $this->instituicao->update([
            'assinatura_status' => Instituicao::ASSINATURA_ACTIVE,
            'id_plano_assinatura' => $plano->id,
            'assinatura_inicia_em' => '2026-01-01',
        ]);

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/contas/consolidado')
            ->assertForbidden()
            ->assertJsonPath('code', 'modulo_nao_incluido');

        $this->getJson('/api/aluno/me/dependentes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/dependentes')
            ->assertUnauthorized();
    }

    public function test_responsavel_de_outra_instituicao_recebe_403(): void
    {
        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'responsavel', ['nome' => 'Responsável de Fora']);
        $this->naInstituicao($outra, fn () => Responsavel::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/aluno/me/dependentes')
            ->assertForbidden();
    }

    private function verComo(int $idAluno, string $turma, string $material, string $conta): void
    {
        $consulta = fn (string $url) => $url.(str_contains($url, '?') ? '&' : '?').'id_aluno='.$idAluno;

        $painel = $this->getJson($consulta('/api/aluno/me/painel'))->assertOk()->getContent();
        $this->assertStringContainsString($turma, $painel);
        $this->assertStringNotContainsString($turma === 'Turma Ana' ? 'Turma Carla' : 'Turma Ana', $painel);

        $matriculas = $this->getJson($consulta('/api/aluno/me/matriculas'))->assertOk()->getContent();
        $this->assertStringContainsString($turma, $matriculas);

        $agenda = $this->getJson($consulta('/api/aluno/me/agenda?inicio=2026-10-26&fim=2026-11-01'))->assertOk()->getContent();
        $this->assertStringContainsString($turma, $agenda);

        $frequencia = $this->getJson($consulta('/api/aluno/me/frequencia'))->assertOk()->getContent();
        $this->assertStringContainsString($turma, $frequencia);

        $materiais = $this->getJson($consulta('/api/aluno/me/materiais'))->assertOk()->getContent();
        $this->assertStringContainsString($material, $materiais);
        $this->assertStringNotContainsString('Partitura do Bruno', $materiais);

        $contas = $this->getJson($consulta('/api/aluno/me/contas?situacao=em_aberto'))->assertOk()->getContent();
        $this->assertStringContainsString($conta, $contas);
        $this->assertStringNotContainsString('Mensalidade do Bruno', $contas);
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

    private function matricular(Aluno $aluno, Turma $turma): Matricula
    {
        return Matricula::create([
            'id_aluno' => $aluno->id,
            'id_turma' => $turma->id,
            'tipo' => 'turma',
            'data' => '2026-09-01',
            'status' => 'ativa',
        ]);
    }

    private function aula(Turma $turma, Professor $professor): AulaTurma
    {
        return AulaTurma::create([
            'id_turma' => $turma->id,
            'id_curso' => $turma->id_curso,
            'id_professor' => $professor->id,
            'data' => '2026-10-27',
            'hora_inicio' => '14:00',
            'hora_termino' => '15:00',
            'status' => 'agendada',
            'tipo' => 'regular',
        ]);
    }
}
