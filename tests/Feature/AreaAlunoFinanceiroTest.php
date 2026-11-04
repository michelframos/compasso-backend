<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\User;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\ConfiguracaoPix;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaPagamento;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class AreaAlunoFinanceiroTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $usuarioAna;

    private User $usuarioRita;

    private Aluno $ana;

    private Aluno $bruno;

    private Conta $atrasada;

    private Conta $aVencer;

    private Conta $paga;

    private Conta $doBruno;

    private ContaPagamento $pagamento;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00');

        $this->instituicao = $this->instituicaoDefault();
        $this->usuarioAna = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Ana Lima']);
        $usuarioBruno = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno', ['nome' => 'Bruno Melo']);
        $this->usuarioRita = $this->criarUsuarioNaInstituicao($this->instituicao, 'responsavel', ['nome' => 'Rita Lima']);

        $this->naInstituicao($this->instituicao, function () use ($usuarioBruno): void {
            $this->ana = Aluno::create(['id_usuario' => $this->usuarioAna->id]);
            $this->bruno = Aluno::create(['id_usuario' => $usuarioBruno->id]);
            $rita = Responsavel::create(['id_usuario' => $this->usuarioRita->id]);
            $this->ana->responsaveis()->attach($rita->id, InstituicaoContext::pivotAttributes(['parentesco' => 'mãe']));

            $categoria = CategoriaConta::firstOrCreate(['nome' => 'Mensalidades'], ['tipo' => 'receita']);
            $this->matricula = Matricula::create([
                'id_aluno' => $this->ana->id,
                'tipo' => 'curso',
                'data' => '2026-09-01',
                'status' => 'ativa',
            ]);

            ConfiguracaoPix::create([
                'chave_pix' => 'escola@compasso.test',
                'tipo_chave' => 'email',
                'nome_beneficiario' => 'Escola Compasso',
                'cidade' => 'Sao Paulo',
                'exibir_qrcode' => true,
            ]);

            $this->atrasada = $this->conta($categoria->id, $this->ana->id, 'Mensalidade da Ana', 100, '2026-10-01', 'pendente', 'segredo da secretaria');
            $this->aVencer = $this->conta($categoria->id, $this->ana->id, 'Mensalidade novembro', 80, '2026-11-10', 'pendente', null, $this->matricula->id);
            $this->paga = $this->conta($categoria->id, $this->ana->id, 'Mensalidade de setembro', 150, '2026-09-10', 'pago');
            $this->conta($categoria->id, $this->ana->id, 'Mensalidade cancelada', 70, '2026-08-10', 'cancelado');
            $this->conta($categoria->id, $this->ana->id, 'Despesa interna', 999, '2026-10-01', 'pendente', null, null, 'despesa');
            $this->doBruno = $this->conta($categoria->id, $this->bruno->id, 'Mensalidade do Bruno', 90, '2026-10-05', 'pendente');

            $this->pagamento = ContaPagamento::create([
                'id_conta' => $this->paga->id,
                'valor_pago' => 150,
                'data_pagamento' => '2026-09-10',
                'forma_pagamento' => 'pix',
                'observacoes' => 'nota interna do caixa',
            ]);
        });
    }

    public function test_aluno_ve_as_proprias_mensalidades_por_situacao(): void
    {
        $abertas = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/contas?situacao=em_aberto')
            ->assertOk();

        $this->assertSame(
            ['Mensalidade da Ana', 'Mensalidade novembro'],
            collect($abertas->json('data'))->pluck('descricao')->all()
        );
        $this->assertSame('atrasada', $abertas->json('data.0.situacao'));
        $this->assertSame('a_vencer', $abertas->json('data.1.situacao'));
        $this->assertEquals(100, $abertas->json('data.0.saldo_devedor'));
        $this->assertArrayNotHasKey('observacoes', $abertas->json('data.0'));

        $corpo = $abertas->getContent();
        $this->assertStringNotContainsString('segredo da secretaria', $corpo);
        $this->assertStringNotContainsString('Mensalidade do Bruno', $corpo);
        $this->assertStringNotContainsString('Despesa interna', $corpo);
        $this->assertStringNotContainsString('Mensalidade cancelada', $corpo);

        $atrasadas = $this->getJson('/api/aluno/me/contas?situacao=atrasadas')->assertOk()->json('data');
        $this->assertSame(['Mensalidade da Ana'], collect($atrasadas)->pluck('descricao')->all());

        $pagas = $this->getJson('/api/aluno/me/contas?situacao=pagas')->assertOk()->json('data');
        $this->assertSame(['Mensalidade de setembro'], collect($pagas)->pluck('descricao')->all());
        $this->assertSame($this->pagamento->id, $pagas[0]['pagamentos'][0]['id']);
        $this->assertSame('pix', $pagas[0]['pagamentos'][0]['forma_pagamento']);
        $this->assertArrayNotHasKey('observacoes', $pagas[0]['pagamentos'][0]);
        $this->assertStringNotContainsString('nota interna do caixa', json_encode($pagas));

        $daMatricula = $this->getJson('/api/aluno/me/contas?situacao=em_aberto&id_matricula='.$this->matricula->id)
            ->assertOk()
            ->json('data');
        $this->assertSame(['Mensalidade novembro'], collect($daMatricula)->pluck('descricao')->all());

        $this->getJson('/api/aluno/me/contas?situacao=todas')->assertStatus(422);
    }

    public function test_pix_carne_e_recibo_ficam_restritos_as_contas_do_aluno(): void
    {
        $pix = $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/contas/'.$this->atrasada->id.'/pix')
            ->assertOk()
            ->json('data');

        $this->assertStringContainsString('100.00', $pix['payload']);
        $this->assertStringStartsWith("\x89PNG", base64_decode($pix['qr_code']));
        $this->assertEquals(100, $pix['valor']);

        $this->getJson('/api/aluno/me/contas/'.$this->paga->id.'/pix')->assertStatus(422);
        $this->getJson('/api/aluno/me/contas/'.$this->doBruno->id.'/pix')->assertForbidden();
        $this->getJson('/api/aluno/me/contas/99999/pix')->assertNotFound();

        $carne = $this->postJson('/api/aluno/me/carne', [
            'conta_ids' => [$this->atrasada->id, $this->aVencer->id],
        ])->assertOk();
        $this->assertStringContainsString('pdf', (string) $carne->headers->get('content-type'));

        $this->postJson('/api/aluno/me/carne', [
            'conta_ids' => [$this->atrasada->id, $this->doBruno->id],
        ])->assertForbidden();

        $recibo = $this->get('/api/aluno/me/pagamentos/'.$this->pagamento->id.'/recibo')->assertOk();
        $this->assertStringContainsString('pdf', (string) $recibo->headers->get('content-type'));

        $this->getJson('/api/aluno/me/pagamentos/99999/recibo')->assertNotFound();
    }

    public function test_sem_o_modulo_financeiro_recebe_403(): void
    {
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

        $this->comoUsuario($this->usuarioAna, $this->instituicao)
            ->getJson('/api/aluno/me/contas')
            ->assertForbidden()
            ->assertJsonPath('code', 'modulo_nao_incluido');
    }

    public function test_responsavel_consulta_o_dependente_e_os_demais_papeis_nao(): void
    {
        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/contas')
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_aluno');

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/contas?situacao=atrasadas&id_aluno='.$this->ana->id)
            ->assertOk()
            ->assertJsonPath('data.0.descricao', 'Mensalidade da Ana');

        $this->comoUsuario($this->usuarioRita, $this->instituicao)
            ->getJson('/api/aluno/me/contas?id_aluno='.$this->bruno->id)
            ->assertForbidden();

        $this->getJson('/api/aluno/me/contas/'.$this->doBruno->id.'/pix?id_aluno='.$this->ana->id)
            ->assertForbidden();

        foreach (['professor', 'secretaria', 'admin'] as $role) {
            $usuario = $this->criarUsuarioNaInstituicao($this->instituicao, $role);
            $this->comoUsuario($usuario, $this->instituicao)
                ->getJson('/api/aluno/me/contas')
                ->assertForbidden();
        }
    }

    public function test_sem_token_retorna_401(): void
    {
        $this->withHeader('X-Tenant-Slug', $this->instituicao->slug)
            ->getJson('/api/aluno/me/contas')
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
            ->getJson('/api/aluno/me/contas')
            ->assertForbidden();
    }

    private function conta(
        int $categoriaId,
        int $alunoId,
        string $descricao,
        float $valor,
        string $vencimento,
        string $status,
        ?string $observacoes = null,
        ?int $idMatricula = null,
        string $tipo = 'receita',
    ): Conta {
        return Conta::create([
            'id_categoria' => $categoriaId,
            'id_aluno' => $alunoId,
            'id_matricula' => $idMatricula,
            'descricao' => $descricao,
            'valor' => $valor,
            'data_vencimento' => $vencimento,
            'status' => $status,
            'tipo' => $tipo,
            'observacoes' => $observacoes,
        ]);
    }
}
