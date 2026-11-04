<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Financeiro\Models\Conta;

/**
 * Integração Bilheteria → Financeiro (cobrança de ingresso).
 *
 * @param  array{
 *     ingresso_id: int,
 *     descricao: string,
 *     valor: float|int|string,
 *     data_vencimento: string,
 *     observacoes?: string|null
 * }  $params
 */
interface CriarCobrancaIngressoPort
{
    public function criar(array $params): Conta;

    public function cancelar(int $idConta, string $observacao = ''): bool;

    public function sincronizarStatusConta(Conta $conta): void;
}
