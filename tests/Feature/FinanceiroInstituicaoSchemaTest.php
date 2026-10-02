<?php

namespace Tests\Feature;

use App\Models\CategoriaConta;
use App\Models\Conta;
use App\Models\Contrato;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceiroInstituicaoSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function defaultInstituicaoId(): int
    {
        return (int) Instituicao::where('slug', 'default')->value('id');
    }

    public function test_tabelas_financeiro_possuem_id_instituicao(): void
    {
        $tables = [
            'contas',
            'conta_pagamentos',
            'categorias_contas',
            'contratos',
            'alunos_contratos',
            'configuracoes_pix',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'id_instituicao'), "Coluna id_instituicao ausente em {$table}");
        }
    }

    public function test_novos_registros_recebem_instituicao_default(): void
    {
        $instituicaoId = $this->defaultInstituicaoId();

        $categoria = CategoriaConta::factory()->create();
        $conta = Conta::factory()->create(['id_categoria' => $categoria->id]);
        $contrato = Contrato::create([
            'nome' => 'Contrato Teste',
            'conteudo' => 'Conteúdo do contrato',
        ]);

        $this->assertSame($instituicaoId, (int) DB::table('categorias_contas')->where('id', $categoria->id)->value('id_instituicao'));
        $this->assertSame($instituicaoId, (int) DB::table('contas')->where('id', $conta->id)->value('id_instituicao'));
        $this->assertSame($instituicaoId, (int) DB::table('contratos')->where('id', $contrato->id)->value('id_instituicao'));
    }

    public function test_configuracoes_pix_podem_existir_por_instituicao(): void
    {
        $outraInstituicao = Instituicao::create([
            'slug' => 'escola-financeiro',
            'nome_fantasia' => 'Escola Financeiro',
            'status' => Instituicao::STATUS_ATIVO,
        ]);

        DB::table('configuracoes_pix')->insert([
            'id_instituicao' => $this->defaultInstituicaoId(),
            'chave_pix' => '11111111111',
            'tipo_chave' => 'cpf',
            'nome_beneficiario' => 'Escola A',
            'cidade' => 'Sao Paulo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('configuracoes_pix')->insert([
            'id_instituicao' => $outraInstituicao->id,
            'chave_pix' => '22222222222',
            'tipo_chave' => 'cpf',
            'nome_beneficiario' => 'Escola B',
            'cidade' => 'Rio',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(2, DB::table('configuracoes_pix')->count());
    }
}
