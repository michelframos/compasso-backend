<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\AlunoContrato;
use App\Modules\Financeiro\Models\Contrato;
use App\Modules\Instrumentos\Models\Instrumento;
use App\Modules\Instrumentos\Models\InstrumentoHistorico;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoInstrumentosTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $bruno;

    private AlunoContrato $termoAna;

    private AlunoContrato $termoRita;

    private AlunoContrato $termoVazio;

    private AlunoContrato $termoBruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno): void {
            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $violino = Instrumento::create([
                'nome' => 'Violino da Ana',
                'tipo' => 'Cordas',
                'numero_serie' => 'VS-1',
                'status' => 'emprestado',
                'id_aluno' => $this->ana->id,
                'data_emprestimo' => '2026-10-01',
                'data_prevista_devolucao' => '2026-12-01',
                'observacoes' => 'segredo do estoque',
            ]);
            $flauta = Instrumento::create([
                'nome' => 'Flauta devolvida',
                'tipo' => 'Sopro',
                'status' => 'disponivel',
            ]);
            Instrumento::create([
                'nome' => 'Violão do Bruno',
                'tipo' => 'Cordas',
                'status' => 'emprestado',
                'id_aluno' => $this->bruno->id,
                'data_emprestimo' => '2026-09-01',
                'observacoes' => 'nota do Bruno',
            ]);

            InstrumentoHistorico::create([
                'id_instrumento' => $violino->id,
                'id_aluno' => $this->ana->id,
                'acao' => 'emprestimo',
                'data' => '2026-10-01 09:00:00',
                'observacoes' => 'nota interna do emprestimo',
            ]);
            InstrumentoHistorico::create([
                'id_instrumento' => $flauta->id,
                'id_aluno' => $this->ana->id,
                'acao' => 'devolucao',
                'data' => '2026-08-01 09:00:00',
                'observacoes' => 'nota interna da devolucao',
            ]);
            InstrumentoHistorico::create([
                'id_instrumento' => $flauta->id,
                'id_aluno' => $this->bruno->id,
                'acao' => 'emprestimo',
                'data' => '2026-07-01 09:00:00',
                'observacoes' => 'nota do historico do Bruno',
            ]);

            $modelo = Contrato::create(['nome' => 'Termo de responsabilidade', 'conteudo' => '<p>Modelo</p>']);
            $this->termoAna = AlunoContrato::create([
                'aluno_id' => $this->ana->id,
                'contrato_id' => $modelo->id,
                'contrato_gerado' => '<p>Termo da Ana</p>',
            ]);
            $this->termoRita = AlunoContrato::create([
                'aluno_id' => $this->ana->id,
                'contrato_id' => $modelo->id,
                'contrato_gerado' => '<p>Termo para a Rita aceitar</p>',
            ]);
            $this->termoVazio = AlunoContrato::create([
                'aluno_id' => $this->ana->id,
                'contrato_id' => $modelo->id,
                'contrato_gerado' => null,
            ]);
            $this->termoBruno = AlunoContrato::create([
                'aluno_id' => $this->bruno->id,
                'contrato_id' => $modelo->id,
                'contrato_gerado' => '<p>Termo do Bruno</p>',
            ]);
        });
    }

    public function test_lista_o_instrumento_atual_e_o_historico_do_aluno(): void
    {
        $dados = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/instrumentos')
            ->assertOk()
            ->json('data');

        $this->assertSame(['Violino da Ana'], collect($dados['atuais'])->pluck('nome')->all());
        $this->assertSame('2026-12-01', $dados['atuais'][0]['data_prevista_devolucao']);
        $this->assertSame('VS-1', $dados['atuais'][0]['numero_serie']);
        $this->assertSame(['emprestimo', 'devolucao'], collect($dados['historico'])->pluck('acao')->all());
        $this->assertSame('Flauta devolvida', $dados['historico'][1]['instrumento']['nome']);

        $corpo = json_encode($dados);
        $this->assertStringNotContainsString('Violão do Bruno', $corpo);
        $this->assertStringNotContainsString('segredo do estoque', $corpo);
        $this->assertStringNotContainsString('nota interna', $corpo);
        $this->assertStringNotContainsString('nota do Bruno', $corpo);

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/instrumentos')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $this->getJson('/api/aluno/me/instrumentos?id_aluno='.$this->ana->id)
            ->assertOk()
            ->assertJsonPath('data.atuais.0.nome', 'Violino da Ana');
        $this->getJson('/api/aluno/me/instrumentos?id_aluno='.$this->bruno->id)->assertForbidden();
    }

    public function test_emprestar_grava_a_devolucao_prevista_e_devolver_limpa(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $instrumento = $this->naInstituicao($this->instituicao, fn () => Instrumento::create([
            'nome' => 'Cello reserva',
            'tipo' => 'Cordas',
            'status' => 'disponivel',
        ]));

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/instrumentos/'.$instrumento->id.'/emprestar', [
                'id_aluno' => $this->ana->id,
                'data_emprestimo' => '2026-10-10',
                'data_prevista_devolucao' => '2026-10-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('data_prevista_devolucao');

        $this->postJson('/api/instrumentos/'.$instrumento->id.'/emprestar', [
            'id_aluno' => $this->ana->id,
            'data_emprestimo' => '2026-10-10',
            'data_prevista_devolucao' => '2027-01-15',
        ])->assertOk();

        $emprestado = $instrumento->fresh();
        $this->assertSame('2027-01-15', substr((string) $emprestado->data_prevista_devolucao, 0, 10));
        $this->assertSame('emprestado', $emprestado->status);

        $this->postJson('/api/instrumentos/'.$instrumento->id.'/devolver')->assertOk();

        $devolvido = $instrumento->fresh();
        $this->assertNull($devolvido->data_prevista_devolucao);
        $this->assertNull($devolvido->id_aluno);
        $this->assertSame('disponivel', $devolvido->status);
    }

    public function test_contrato_e_aceito_pelo_aluno_ou_responsavel_e_sai_em_pdf(): void
    {
        $lista = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/contratos')
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $lista);
        $corpo = json_encode($lista);
        $this->assertStringContainsString('Termo da Ana', $corpo);
        $this->assertStringNotContainsString('Termo do Bruno', $corpo);
        $this->assertStringNotContainsString('hash_conteudo', $corpo);
        $this->assertStringNotContainsString('ip_aceite', $corpo);

        $this->withHeader('User-Agent', 'CompassoTest/1.0')
            ->postJson('/api/aluno/me/contratos/'.$this->termoAna->id.'/aceite')
            ->assertOk()
            ->assertJsonPath('data.aceito', true);

        $aceito = $this->termoAna->fresh();
        $this->assertNotNull($aceito->aceito_em);
        $this->assertSame($this->usuarioAna->id, $aceito->aceito_por_usuario_id);
        $this->assertSame(hash('sha256', '<p>Termo da Ana</p>'), $aceito->hash_conteudo);
        $this->assertSame('127.0.0.1', $aceito->ip_aceite);
        $this->assertSame('CompassoTest/1.0', $aceito->user_agent_aceite);

        $this->postJson('/api/aluno/me/contratos/'.$this->termoAna->id.'/aceite')
            ->assertStatus(422);

        $this->postJson('/api/aluno/me/contratos/'.$this->termoVazio->id.'/aceite')
            ->assertStatus(422);
        $this->postJson('/api/aluno/me/contratos/'.$this->termoBruno->id.'/aceite')
            ->assertForbidden();

        $pdf = $this->get('/api/aluno/me/contratos/'.$this->termoAna->id.'/pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get('/api/aluno/me/contratos/'.$this->termoBruno->id.'/pdf')->assertForbidden();

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/contratos')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $this->postJson('/api/aluno/me/contratos/'.$this->termoRita->id.'/aceite?id_aluno='.$this->ana->id)
            ->assertOk()
            ->assertJsonPath('data.aceito', true);

        $this->assertSame($this->usuarioRita->id, $this->termoRita->fresh()->aceito_por_usuario_id);
        $this->postJson('/api/aluno/me/contratos/'.$this->termoBruno->id.'/aceite?id_aluno='.$this->ana->id)
            ->assertForbidden();
        $this->getJson('/api/aluno/me/contratos?id_aluno='.$this->bruno->id)->assertForbidden();
    }

    public function test_sem_modulo_de_instrumentos_os_contratos_continuam(): void
    {
        $plano = PlanoAssinatura::create([
            'slug' => 'sem-instrumentos',
            'nome' => 'Sem instrumentos',
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
            ->getJson('/api/aluno/me/instrumentos')
            ->assertForbidden()
            ->assertJsonPath('code', 'modulo_nao_incluido');

        $this->getJson('/api/aluno/me/contratos')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_demais_papeis_e_sessao_recebem_401_ou_403(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/instrumentos')
            ->assertUnauthorized();
        $this->getJson('/api/aluno/me/contratos')->assertUnauthorized();

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/instrumentos')
                ->assertForbidden();
            $this->getJson('/api/aluno/me/contratos')->assertForbidden();
        }

        $outra = Instituicao::create([
            'slug' => 'outra-escola',
            'nome_fantasia' => 'Outra Escola',
            'status' => Instituicao::STATUS_ATIVO,
        ]);
        $usuarioOutro = $this->criarUsuarioNaInstituicao($outra, 'aluno', ['nome' => 'Aluno de Fora']);
        $this->naInstituicao($outra, fn () => Aluno::create(['id_usuario' => $usuarioOutro->id]));

        $this->comoUsuario($usuarioOutro, $this->instituicao)
            ->getJson('/api/aluno/me/instrumentos')
            ->assertForbidden();
        $this->getJson('/api/aluno/me/contratos')->assertForbidden();
    }
}
