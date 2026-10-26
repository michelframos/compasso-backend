<?php

namespace Tests\Feature;

use App\Enums\TurmaStatus;
use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\ApresentacaoAluno;
use App\Modules\Espetaculos\Models\Ensaio;
use App\Modules\Espetaculos\Models\Espetaculo;
use App\Modules\Pessoas\Models\MedidaAluno;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoApresentacoesTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $bruno;

    private MedidaAluno $medidaAna;

    private MedidaAluno $medidaBruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);
        $usuarioProfessor = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['nome' => 'Paula Prof']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno, $usuarioProfessor): void {
            $curso = Curso::create(['nome' => 'Violão', 'descricao' => 'Cordas']);
            $nivel = Nivel::create(['nome' => 'Básico']);
            $professor = Professor::create(['id_usuario' => $usuarioProfessor->id]);
            $turmaAna = $this->turma($curso, $nivel, $professor, 'Turma Ana');
            $turmaSarau = $this->turma($curso, $nivel, $professor, 'Turma do sarau');

            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, \App\Modules\Core\Support\InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $recital = Espetaculo::create([
                'titulo' => 'Recital',
                'data_evento' => '2026-10-20',
                'local' => 'Teatro',
                'status' => 'planejamento',
                'observacoes' => 'segredo do palco',
            ]);
            $gala = Espetaculo::create([
                'titulo' => 'Gala',
                'data_evento' => '2026-11-20',
                'local' => 'Auditório',
                'status' => 'ensaios',
            ]);
            $sarau = Espetaculo::create([
                'titulo' => 'Sarau',
                'data_evento' => '2026-12-01',
                'local' => 'Praça',
                'status' => 'planejamento',
            ]);

            $pecaAna = Apresentacao::create(['id_espetaculo' => $recital->id, 'id_turma' => $turmaAna->id, 'titulo_musica' => 'Peça da Ana']);
            Apresentacao::create(['id_espetaculo' => $recital->id, 'id_turma' => $turmaAna->id, 'titulo_musica' => 'Peça da turma']);
            Apresentacao::create(['id_espetaculo' => $gala->id, 'id_turma' => $turmaAna->id, 'titulo_musica' => 'Peça da gala']);
            $convidada = Apresentacao::create(['id_espetaculo' => $sarau->id, 'id_turma' => $turmaSarau->id, 'titulo_musica' => 'Peça convidada']);

            ApresentacaoAluno::create([
                'id_apresentacao' => $pecaAna->id,
                'id_aluno' => $this->ana->id,
                'papel' => 'Solista',
                'tamanho_figurino' => 'P',
                'recebeu_figurino' => true,
                'valor_figurino' => 150.75,
            ]);
            ApresentacaoAluno::create([
                'id_apresentacao' => $pecaAna->id,
                'id_aluno' => $this->bruno->id,
                'papel' => 'Coro',
            ]);
            $galaAna = Apresentacao::query()->where('titulo_musica', 'Peça da gala')->firstOrFail();
            ApresentacaoAluno::create(['id_apresentacao' => $galaAna->id, 'id_aluno' => $this->ana->id, 'papel' => 'Coro']);
            ApresentacaoAluno::create(['id_apresentacao' => $convidada->id, 'id_aluno' => $this->ana->id, 'papel' => 'Convidada']);

            Ensaio::create([
                'id_apresentacao' => $pecaAna->id,
                'id_professor' => $professor->id,
                'data' => '2026-10-18',
                'hora_inicio' => '10:00',
                'hora_termino' => '11:00',
                'local' => 'Sala 2',
                'observacoes' => 'nota interna do ensaio',
            ]);

            MedidaAluno::create(['id_aluno' => $this->ana->id, 'medida_altura' => 150, 'ativo' => false]);
            $this->medidaAna = MedidaAluno::create(['id_aluno' => $this->ana->id, 'medida_altura' => 160, 'medida_torax' => 70, 'ativo' => true]);
            $this->medidaBruno = MedidaAluno::create(['id_aluno' => $this->bruno->id, 'medida_altura' => 175, 'ativo' => true]);
        });
    }

    public function test_lista_so_as_apresentacoes_em_que_o_aluno_esta_escalado(): void
    {
        $lista = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/apresentacoes')
            ->assertOk()
            ->json('data');

        $this->assertSame(['Peça da Ana', 'Peça da gala', 'Peça convidada'], collect($lista)->pluck('titulo_musica')->all());
        $this->assertSame('2026-10-20', $lista[0]['espetaculo']['data_evento']);
        $this->assertSame('Teatro', $lista[0]['espetaculo']['local']);
        $this->assertSame('Solista', $lista[0]['papel']);
        $this->assertSame('P', $lista[0]['tamanho_figurino']);
        $this->assertTrue($lista[0]['recebeu_figurino']);
        $this->assertSame('Sala 2', $lista[0]['ensaios'][0]['local']);
        $this->assertSame('10:00', $lista[0]['ensaios'][0]['hora_inicio']);

        $corpo = json_encode($lista);
        $this->assertStringNotContainsString('Peça da turma', $corpo);
        $this->assertStringNotContainsString('Bruno Melo', $corpo);
        $this->assertStringNotContainsString('valor_figurino', $corpo);
        $this->assertStringNotContainsString('150.75', $corpo);
        $this->assertStringNotContainsString('segredo do palco', $corpo);
        $this->assertStringNotContainsString('nota interna do ensaio', $corpo);

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/apresentacoes')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $daRita = $this->getJson('/api/aluno/me/apresentacoes?id_aluno='.$this->ana->id)->assertOk()->json('data');
        $this->assertSame('Peça da Ana', $daRita[0]['titulo_musica']);

        $this->getJson('/api/aluno/me/apresentacoes?id_aluno='.$this->bruno->id)->assertForbidden();
    }

    public function test_medidas_sao_do_aluno_e_a_edicao_depende_da_escola(): void
    {
        $resposta = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/medidas')
            ->assertOk()
            ->assertJsonPath('pode_editar', false)
            ->assertJsonPath('data.0.medida_altura', 160);

        $this->assertCount(2, $resposta->json('data'));
        $this->assertStringNotContainsString('175', $resposta->getContent());

        $this->putJson('/api/aluno/me/medidas/'.$this->medidaAna->id, [
            'medida_altura' => 161.5,
            'ativo' => false,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'A escola não permite que o aluno altere as medidas.');

        $this->assertEquals(160, (float) $this->medidaAna->fresh()->medida_altura);

        $this->instituicao->update(['permitir_aluno_editar_medidas' => true]);

        $this->putJson('/api/aluno/me/medidas/'.$this->medidaAna->id, [
            'medida_altura' => 161.5,
            'ativo' => false,
            'id_aluno' => $this->ana->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.medida_altura', 161.5)
            ->assertJsonPath('data.ativo', true)
            ->assertJsonPath('data.id_aluno', $this->ana->id);

        $this->putJson('/api/aluno/me/medidas/'.$this->medidaBruno->id, ['medida_altura' => 180])
            ->assertForbidden();

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/medidas')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $this->putJson('/api/aluno/me/medidas/'.$this->medidaAna->id.'?id_aluno='.$this->ana->id, [
            'medida_cintura' => 62,
        ])
            ->assertOk()
            ->assertJsonPath('data.medida_cintura', 62)
            ->assertJsonPath('data.medida_altura', 161.5);

        $this->putJson('/api/aluno/me/medidas/'.$this->medidaBruno->id.'?id_aluno='.$this->ana->id, [
            'medida_altura' => 180,
        ])->assertForbidden();

        $this->getJson('/api/aluno/me/medidas?id_aluno='.$this->bruno->id)->assertForbidden();
    }

    public function test_sem_modulo_as_apresentacoes_somem_e_as_medidas_continuam(): void
    {
        $plano = PlanoAssinatura::create([
            'slug' => 'sem-espetaculos',
            'nome' => 'Sem espetáculos',
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

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/apresentacoes')
            ->assertForbidden()
            ->assertJsonPath('code', 'modulo_nao_incluido');

        $this->getJson('/api/aluno/me/medidas')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_demais_papeis_e_sessao_recebem_401_ou_403(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/apresentacoes')
            ->assertUnauthorized();

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/apresentacoes')
                ->assertForbidden();
            $this->getJson('/api/aluno/me/medidas')->assertForbidden();
        }

        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'aluno', ['nome' => 'Aluno de Fora']);
        $this->naInstituicao($outra, fn () => Aluno::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/aluno/me/apresentacoes')
            ->assertForbidden();
        $this->getJson('/api/aluno/me/medidas')->assertForbidden();
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
}
