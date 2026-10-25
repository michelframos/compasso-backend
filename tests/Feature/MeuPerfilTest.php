<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class MeuPerfilTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private int $estado;

    private int $cidade;

    private int $cidadeDeOutroEstado;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->instituicao = $this->instituicaoDefault();

        $this->estado = DB::table('estados')->insertGetId(['nome' => 'São Paulo', 'sigla' => 'SP']);
        $outroEstado = DB::table('estados')->insertGetId(['nome' => 'Minas Gerais', 'sigla' => 'MG']);
        $this->cidade = DB::table('cidades')->insertGetId(['nome' => 'Campinas', 'id_estado' => $this->estado]);
        $this->cidadeDeOutroEstado = DB::table('cidades')->insertGetId(['nome' => 'Uberlândia', 'id_estado' => $outroEstado]);
    }

    private function dadosPerfil(array $sobrescrever = []): array
    {
        return [
            'nome' => 'Maria Souza',
            'telefone' => '(19) 3333-4444',
            'whatsapp' => '(19) 99999-8888',
            'data_aniversario' => '1990-05-20',
            'cep' => '13010-000',
            'rua' => 'Rua das Flores',
            'numero' => '100',
            'complemento' => 'Apto 12',
            'bairro' => 'Centro',
            'id_estado' => $this->estado,
            'id_cidade' => $this->cidade,
            ...$sobrescrever,
        ];
    }

    public function test_todos_os_papeis_consultam_e_atualizam_o_proprio_perfil(): void
    {
        foreach (['professor', 'aluno', 'secretaria', 'admin', 'responsavel'] as $role) {
            $user = $this->criarUsuarioNaInstituicao($this->instituicao, $role);

            $this->comoUsuario($user, $this->instituicao)
                ->getJson('/api/me/perfil')
                ->assertOk()
                ->assertJsonPath('data.id', $user->id)
                ->assertJsonPath('data.email', $user->email);

            $this->putJson('/api/me/perfil', $this->dadosPerfil(['nome' => "Perfil {$role}"]))
                ->assertOk()
                ->assertJsonPath('data.nome', "Perfil {$role}")
                ->assertJsonPath('data.data_aniversario', '1990-05-20')
                ->assertJsonPath('data.id_cidade', $this->cidade);
        }
    }

    public function test_perfil_nao_altera_email_cpf_nem_papel(): void
    {
        $user = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['cpf' => '52998224725']);

        $this->comoUsuario($user, $this->instituicao)
            ->putJson('/api/me/perfil', $this->dadosPerfil([
                'email' => 'novo@example.com',
                'cpf' => '11144477735',
                'role' => 'admin',
                'is_super_admin' => true,
            ]))
            ->assertOk();

        $user->refresh();
        $this->assertSame('Maria Souza', $user->nome);
        $this->assertNotSame('novo@example.com', $user->email);
        $this->assertSame('52998224725', $user->cpf);
        $this->assertSame('professor', $user->role);
        $this->assertFalse($user->is_super_admin);
    }

    public function test_validacao_do_perfil(): void
    {
        $user = $this->criarUsuarioNaInstituicao($this->instituicao, 'aluno');
        $this->comoUsuario($user, $this->instituicao);

        $this->putJson('/api/me/perfil', $this->dadosPerfil(['nome' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nome');

        $this->putJson('/api/me/perfil', $this->dadosPerfil(['id_cidade' => $this->cidadeDeOutroEstado]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_cidade');

        $this->putJson('/api/me/perfil', $this->dadosPerfil(['data_aniversario' => now()->addDay()->format('Y-m-d')]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data_aniversario');
    }

    public function test_envia_substitui_e_remove_foto(): void
    {
        $user = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor');
        $this->comoUsuario($user, $this->instituicao);

        $primeira = $this->post('/api/me/perfil/foto', ['foto' => UploadedFile::fake()->image('eu.jpg', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data');

        Storage::disk('public')->assertExists($primeira['foto']);
        $this->assertStringEndsWith('storage/' . $primeira['foto'], $primeira['foto_url']);

        $segunda = $this->post('/api/me/perfil/foto', ['foto' => UploadedFile::fake()->image('eu2.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data');

        Storage::disk('public')->assertMissing($primeira['foto']);
        Storage::disk('public')->assertExists($segunda['foto']);

        $this->deleteJson('/api/me/perfil/foto')
            ->assertOk()
            ->assertJsonPath('data.foto', null)
            ->assertJsonPath('data.foto_url', null);

        Storage::disk('public')->assertMissing($segunda['foto']);

        $this->post('/api/me/perfil/foto', ['foto' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('foto');
    }

    public function test_exige_autenticacao_e_senha_atualizada(): void
    {
        $this->getJson('/api/me/perfil')->assertUnauthorized();
        $this->putJson('/api/me/perfil', $this->dadosPerfil())->assertUnauthorized();

        $user = $this->criarUsuarioNaInstituicao($this->instituicao, 'professor', ['deve_trocar_senha' => true]);

        $this->comoUsuario($user, $this->instituicao)
            ->getJson('/api/me/perfil')
            ->assertForbidden()
            ->assertJsonPath('code', 'troca_senha_obrigatoria');
        $this->putJson('/api/me/perfil', $this->dadosPerfil())->assertForbidden();
    }
}
