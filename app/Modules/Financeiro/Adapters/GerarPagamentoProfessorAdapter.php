<?php

namespace App\Modules\Financeiro\Adapters;

use App\Modules\Core\Contracts\GerarPagamentoProfessorPort;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\CategoriaConta;
use App\Modules\Financeiro\Models\Conta;

class GerarPagamentoProfessorAdapter implements GerarPagamentoProfessorPort
{
    private const CATEGORIA = 'Pagamento de professores';

    public function gerar(array $params): int
    {
        $categoria = CategoriaConta::firstOrCreate(
            ['nome' => self::CATEGORIA],
            ['tipo' => 'despesa', 'descricao' => 'Remuneração mensal dos professores (fechamento do extrato)'],
        );

        return Conta::create([
            'id_instituicao' => InstituicaoContext::id(),
            'id_categoria' => $categoria->id,
            'id_professor' => $params['id_professor'],
            'descricao' => $params['descricao'],
            'valor' => $params['valor'],
            'data_vencimento' => $params['data_vencimento'],
            'mes_referencia' => $params['mes_referencia'],
            'ano_referencia' => $params['ano_referencia'],
            'observacoes' => $params['observacoes'] ?? null,
            'status' => 'pendente',
            'tipo' => 'despesa',
            'notificar' => false,
        ])->id;
    }

    public function cancelar(int $idConta): bool
    {
        $conta = Conta::find($idConta);

        if (! $conta) {
            return true;
        }

        if ($conta->pagamentos()->exists()) {
            return false;
        }

        $conta->delete();

        return true;
    }
}
