<?php

namespace App\Modules\Core\Contracts;

/**
 * Integração Espetáculos → Financeiro (cobranças de figurino).
 * Fase 6: implementação no Financeiro; Fase 8 limpa o controller de Espetáculos.
 *
 * @param  array{
 *     apresentacao_id: int,
 *     participantes_ids: array<int>,
 *     data_vencimento: string
 * }  $params
 * @return array{contas_geradas: int, message: string}
 */
interface CriarCobrancasFigurinoPort
{
    public function execute(array $params): array;
}
