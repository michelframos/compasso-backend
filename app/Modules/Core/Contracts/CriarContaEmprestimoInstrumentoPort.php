<?php

namespace App\Modules\Core\Contracts;

/**
 * Integração Instrumentos → Financeiro (conta opcional no empréstimo).
 *
 * @param  array{
 *     id_aluno: int,
 *     id_categoria: int,
 *     descricao: string,
 *     valor: float|int|string,
 *     data_vencimento: string
 * }  $params
 */
interface CriarContaEmprestimoInstrumentoPort
{
    public function execute(array $params): object;
}
