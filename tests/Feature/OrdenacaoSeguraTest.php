<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithInstituicao;
use Tests\TestCase;

class OrdenacaoSeguraTest extends TestCase
{
    use InteractsWithInstituicao;
    use RefreshDatabase;

    private Instituicao $instituicao;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituicao = $this->instituicaoDefault();
        $this->admin = $this->criarUsuarioNaInstituicao($this->instituicao, 'admin');
    }

    public static function endpoints(): array
    {
        return [
            'alunos' => ['/api/alunos', ['usuarios.nome', 'usuarios.email', 'cpf']],
            'professores' => ['/api/professores', ['usuarios.nome', 'usuarios.email', 'comissao']],
            'contratos' => ['/api/contratos', ['nome']],
            'turmas' => ['/api/turmas', ['id', 'curso.nome', 'valor_mensalidade']],
            'niveis' => ['/api/niveis', ['nome', 'observacoes']],
            'cursos' => ['/api/cursos', ['nome', 'descricao']],
            'matriculas' => ['/api/matriculas', ['aluno.nome', 'turma.nome', 'data', 'status']],
            'contas' => ['/api/contas', ['descricao', 'valor', 'saldo_devedor', 'data_vencimento', 'status']],
        ];
    }

    /**
     * @dataProvider endpoints
     */
    public function test_chaves_usadas_pelo_frontend_funcionam(string $url, array $chaves): void
    {
        foreach ($chaves as $chave) {
            foreach (['asc', 'desc'] as $ordem) {
                $this->comoUsuario($this->admin, $this->instituicao)
                    ->getJson("{$url}?sort_by={$chave}&sort_order={$ordem}")
                    ->assertOk();
            }
        }
    }

    /**
     * @dataProvider endpoints
     */
    public function test_ordenacao_invalida_cai_no_padrao(string $url): void
    {
        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson("{$url}?sort_by=senha&sort_order=desc;drop")
            ->assertOk();

        $this->comoUsuario($this->admin, $this->instituicao)
            ->getJson("{$url}?sort_by=(select%201)&sort_order=asc")
            ->assertOk();
    }
}
