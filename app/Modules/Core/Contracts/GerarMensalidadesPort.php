<?php

namespace App\Modules\Core\Contracts;

/**
 * Integração Academico → Financeiro (mensalidades da matrícula).
 * Fase 5: adapter legado; Fase 6: implementação no módulo Financeiro.
 *
 * @param  array{
 *     valor: float|int|string,
 *     quantidade_parcelas: int,
 *     dia_vencimento: int,
 *     data_inicio: string,
 *     id_categoria?: int|null,
 *     observacoes?: string|null,
 *     nome_aluno: string,
 *     id_aluno: int,
 *     id_matricula: int
 * }  $params
 * @return array<int, object>
 */
interface GerarMensalidadesPort
{
    public function execute(array $params): array;
}
