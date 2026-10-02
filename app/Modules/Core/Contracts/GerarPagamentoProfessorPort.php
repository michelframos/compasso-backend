<?php

namespace App\Modules\Core\Contracts;

/**
 * Integração Relatorios → Financeiro: despesa do pagamento mensal do professor.
 */
interface GerarPagamentoProfessorPort
{
    /**
     * @param  array{
     *     id_professor: int,
     *     descricao: string,
     *     valor: float,
     *     data_vencimento: string,
     *     mes_referencia: int,
     *     ano_referencia: int,
     *     observacoes?: string|null
     * }  $params
     * @return int id da conta gerada
     */
    public function gerar(array $params): int;

    /** Exclui a despesa; devolve false (sem alterar nada) se ela já tiver pagamentos. */
    public function cancelar(int $idConta): bool;
}
