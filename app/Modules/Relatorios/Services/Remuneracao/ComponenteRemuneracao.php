<?php

namespace App\Modules\Relatorios\Services\Remuneracao;

use App\Modules\Pessoas\Models\Professor;

/**
 * Parte da remuneração mensal do professor. O extrato soma todos os componentes
 * (regra "soma integral": cada professor recebe o que estiver preenchido no cadastro).
 */
interface ComponenteRemuneracao
{
    /** Chave do componente no extrato (ex.: `aulas`, `comissoes`). */
    public function chave(): string;

    /** @return array{total: float, itens: array<int, array<string, mixed>>, resumo: array<string, float|int>} */
    public function calcular(Professor $professor, Competencia $competencia): array;
}
