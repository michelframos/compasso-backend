<?php

namespace Tests\Feature;

use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Espetaculos\Models\Espetaculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class IngressoPdfPublicoTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-04 18:00:00');
        $this->instituicao = $this->instituicaoDefault();
    }

    public function test_secretaria_baixa_pdf_do_ingresso_com_template(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $ingresso = $this->criarIngressoComTemplate($secretaria);

        $pdf = $this->comoUsuario($secretaria, $this->instituicao)
            ->get('/api/ingressos/'.$ingresso->id.'/pdf');

        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_publico_bloqueia_ingresso_disponivel(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $ingresso = $this->criarIngressoComTemplate($secretaria);

        $this->assertSame(Ingresso::STATUS_DISPONIVEL, $ingresso->status);

        $this->getJson('/api/public/ingressos/'.$ingresso->codigo_qr)
            ->assertNotFound();

        $this->get('/api/public/ingressos/'.$ingresso->codigo_qr.'/pdf')
            ->assertNotFound();
    }

    public function test_publico_libera_apos_venda(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $ingresso = $this->criarIngressoComTemplate($secretaria);

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/ingressos/'.$ingresso->id.'/vender', [
                'comprador_nome' => 'Maria',
            ])
            ->assertOk()
            ->assertJsonPath('data.url_publica', '/ingresso/'.$ingresso->codigo_qr);

        $publico = $this->getJson('/api/public/ingressos/'.$ingresso->codigo_qr)
            ->assertOk()
            ->assertJsonPath('data.numero', $ingresso->numero)
            ->assertJsonPath('data.comprador_nome', 'Maria')
            ->assertJsonPath('data.espetaculo.titulo', 'Show PDF')
            ->assertJsonPath('data.tem_template', true);

        $this->assertNotEmpty($publico->json('data.qr_data_uri'));
        $this->assertStringStartsWith('data:image/png;base64,', $publico->json('data.qr_data_uri'));
        $this->assertNotEmpty($publico->json('data.layout.html'));
        $this->assertGreaterThan(0, $publico->json('data.layout.largura_px'));
        $this->assertGreaterThan(0, $publico->json('data.layout.altura_px'));

        $pdf = $this->get('/api/public/ingressos/'.$ingresso->codigo_qr.'/pdf');
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_publico_libera_apos_cortesia(): void
    {
        $secretaria = $this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria');
        $ingresso = $this->criarIngressoComTemplate($secretaria);

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/ingressos/'.$ingresso->id.'/cortesia')
            ->assertOk();

        $this->getJson('/api/public/ingressos/'.$ingresso->codigo_qr)
            ->assertOk()
            ->assertJsonPath('data.cortesia', true);
    }

    public function test_codigo_inexistente_retorna_404(): void
    {
        $this->getJson('/api/public/ingressos/codigo-inexistente')
            ->assertNotFound();
    }

    private function criarIngressoComTemplate($secretaria): Ingresso
    {
        $templateRes = $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/templates-ingressos', [
                'nome' => 'Ticket PDF',
                'largura' => 15,
                'altura' => 7,
                'unidade' => 'cm',
                'conteudo' => json_encode([
                    'version' => 1,
                    'elementos' => [[
                        'id' => 'el1',
                        'tipo' => 'texto',
                        'x' => 5,
                        'y' => 5,
                        'w' => 60,
                        'h' => 20,
                        'texto' => 'N {{numero}}',
                        'fonteTamanho' => 14,
                        'fontePeso' => 'bold',
                        'alinhamento' => 'left',
                        'cor' => '#000',
                    ]],
                ]),
            ])
            ->assertCreated();

        $templateId = $templateRes->json('data.id');

        $espetaculo = $this->naInstituicao($this->instituicao, fn () => Espetaculo::create([
            'titulo' => 'Show PDF',
            'data_evento' => '2026-11-04',
            'hora_evento' => '20:00',
            'local' => 'Teatro',
            'id_template_ingresso' => $templateId,
            'valor_ingresso_padrao' => 40,
        ]));

        $this->comoUsuario($secretaria, $this->instituicao)
            ->postJson('/api/espetaculos/'.$espetaculo->id.'/ingressos/gerar', [
                'quantidade' => 1,
                'id_template_ingresso' => $templateId,
            ])
            ->assertCreated();

        return Ingresso::query()->firstOrFail();
    }
}
