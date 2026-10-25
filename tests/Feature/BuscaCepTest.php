<?php

namespace Tests\Feature;

use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class BuscaCepTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private int $estado;

    private int $cidade;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.cep.driver' => 'viacep', 'services.cep.viacep_url' => 'https://viacep.test/ws']);

        $this->instituicao = $this->instituicaoDefault();
        $this->estado = DB::table('estados')->insertGetId(['nome' => 'São Paulo', 'sigla' => 'SP', 'codigo_ibge' => 35]);
        $this->cidade = DB::table('cidades')->insertGetId(['nome' => 'São Paulo', 'id_estado' => $this->estado, 'codigo_ibge' => 3550308]);
    }

    private function respostaViaCep(array $sobrescrever = []): array
    {
        return [
            'cep' => '01001-000',
            'logradouro' => 'Praça da Sé',
            'complemento' => 'lado ímpar',
            'unidade' => '',
            'bairro' => 'Sé',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
            'estado' => 'São Paulo',
            'ibge' => '3550308',
            'ddd' => '11',
            ...$sobrescrever,
        ];
    }

    private function autenticado(): static
    {
        return $this->comoUsuario($this->criarUsuarioNaInstituicao($this->instituicao, 'secretaria'), $this->instituicao);
    }

    public function test_retorna_endereco_com_estado_e_cidade_resolvidos_pelo_codigo_ibge(): void
    {
        Http::fake(['viacep.test/*' => Http::response($this->respostaViaCep())]);

        $this->autenticado()
            ->getJson('/api/cep/01001-000')
            ->assertOk()
            ->assertExactJson(['data' => [
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'complemento' => 'lado ímpar',
                'bairro' => 'Sé',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'codigo_ibge' => 3550308,
                'id_estado' => $this->estado,
                'id_cidade' => $this->cidade,
            ]]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://viacep.test/ws/01001000/json/');
    }

    public function test_resolve_cidade_por_nome_e_uf_quando_codigo_ibge_nao_bate(): void
    {
        $campinas = DB::table('cidades')->insertGetId(['nome' => 'Campinas', 'id_estado' => $this->estado]);

        Http::fake(['viacep.test/*' => Http::response($this->respostaViaCep([
            'cep' => '13010-000', 'logradouro' => '', 'bairro' => '', 'complemento' => '',
            'localidade' => 'Campinas', 'ibge' => '9999999',
        ]))]);

        $this->autenticado()
            ->getJson('/api/cep/13010000')
            ->assertOk()
            ->assertJsonPath('data.cep', '13010-000')
            ->assertJsonPath('data.logradouro', null)
            ->assertJsonPath('data.bairro', null)
            ->assertJsonPath('data.id_estado', $this->estado)
            ->assertJsonPath('data.id_cidade', $campinas);
    }

    public function test_resolve_apenas_o_estado_quando_a_cidade_nao_existe_na_base_local(): void
    {
        $df = DB::table('estados')->insertGetId(['nome' => 'Distrito Federal', 'sigla' => 'DF', 'codigo_ibge' => 53]);

        Http::fake(['viacep.test/*' => Http::response($this->respostaViaCep([
            'cep' => '70040-010', 'logradouro' => 'Quadra SBN Quadra 1', 'bairro' => 'Asa Norte', 'complemento' => '',
            'localidade' => 'Brasília', 'uf' => 'DF', 'ibge' => '5300108',
        ]))]);

        $this->autenticado()
            ->getJson('/api/cep/70040010')
            ->assertOk()
            ->assertJsonPath('data.cidade', 'Brasília')
            ->assertJsonPath('data.id_estado', $df)
            ->assertJsonPath('data.id_cidade', null);
    }

    public function test_cep_inexistente_retorna_404(): void
    {
        Http::fake(['viacep.test/*' => Http::response(['erro' => 'true'])]);

        $this->autenticado()
            ->getJson('/api/cep/99999-999')
            ->assertNotFound()
            ->assertJsonPath('message', 'CEP não encontrado.');
    }

    public function test_cep_em_formato_invalido_nao_consulta_o_provedor(): void
    {
        Http::fake();

        $this->autenticado()->getJson('/api/cep/0100100')->assertNotFound();
        $this->autenticado()->getJson('/api/cep/01001-00A')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_provedor_indisponivel_retorna_503(): void
    {
        Http::fake(['viacep.test/*' => Http::response('Bad Gateway', 502)]);

        $this->autenticado()
            ->getJson('/api/cep/01001000')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Serviço de CEP indisponível. Preencha o endereço manualmente.');

        Http::fake(['viacep.test/*' => fn () => throw new ConnectionException('timeout')]);

        $this->autenticado()->getJson('/api/cep/01002000')->assertStatus(503);
    }

    public function test_cep_encontrado_fica_em_cache_e_nao_encontrado_nao(): void
    {
        Http::fake([
            'viacep.test/ws/01001000/*' => Http::response($this->respostaViaCep()),
            'viacep.test/ws/99999999/*' => Http::response(['erro' => true]),
        ]);

        $this->autenticado()->getJson('/api/cep/01001000')->assertOk();
        $this->autenticado()->getJson('/api/cep/01001-000')->assertOk()->assertJsonPath('data.id_cidade', $this->cidade);
        $this->autenticado()->getJson('/api/cep/99999999')->assertNotFound();
        $this->autenticado()->getJson('/api/cep/99999999')->assertNotFound();

        Http::assertSentCount(3);
    }

    public function test_exige_autenticacao(): void
    {
        Http::fake();

        $this->getJson('/api/cep/01001000')->assertUnauthorized();

        Http::assertNothingSent();
    }
}
