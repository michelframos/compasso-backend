<?php

namespace App\Modules\Core\Contracts;

/**
 * Integração Academico → Financeiro (conta opcional ao criar aula).
 * Fase 5: adapter legado; Fase 6: implementação no módulo Financeiro.
 *
 * @param  array{
 *     id_aluno: int,
 *     id_categoria: int,
 *     id_aula_turma: int,
 *     descricao: string,
 *     valor: float|int|string,
 *     data_vencimento: string,
 *     notificar?: bool
 * }  $params
 */
interface CriarContaAulaPort
{
    public function execute(array $params): object;
}
