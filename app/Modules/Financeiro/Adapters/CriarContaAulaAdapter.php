<?php

namespace App\Modules\Financeiro\Adapters;

use App\Modules\Core\Contracts\CriarContaAulaPort;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Financeiro\Models\Conta;

class CriarContaAulaAdapter implements CriarContaAulaPort
{
    public function execute(array $params): object
    {
        return Conta::create([
            'id_instituicao' => $params['id_instituicao'] ?? InstituicaoContext::id(),
            'id_aluno' => $params['id_aluno'],
            'id_categoria' => $params['id_categoria'],
            'id_aula_turma' => $params['id_aula_turma'],
            'descricao' => $params['descricao'],
            'valor' => $params['valor'],
            'data_vencimento' => $params['data_vencimento'],
            'status' => 'pendente',
            'tipo' => 'receita',
            'notificar' => $params['notificar'] ?? true,
        ]);
    }
}
