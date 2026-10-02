<?php

namespace App\Modules\Relatorios\Services\Remuneracao;

use App\Modules\Pessoas\Models\Professor;

class SalarioFixoComponente implements ComponenteRemuneracao
{
    public function chave(): string
    {
        return 'salario_fixo';
    }

    public function calcular(Professor $professor, Competencia $competencia): array
    {
        $valor = round((float) $professor->salario_fixo, 2);

        return ['total' => $valor, 'itens' => [], 'resumo' => ['salario_fixo' => $valor]];
    }
}
