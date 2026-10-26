<?php

namespace App\Modules\Core\Contracts;

/**
 * Integração Academico → Financeiro (painel e, depois, a área financeira do aluno).
 * Sem o módulo no plano, o adapter devolve o resumo vazio.
 *
 * @phpstan-type MensalidadeAluno array{
 *     id: int,
 *     descricao: string,
 *     valor: float,
 *     data_vencimento: string,
 *     status: string,
 *     situacao: string
 * }
 * @phpstan-type ResumoFinanceiroAluno array{
 *     disponivel: bool,
 *     proxima_mensalidade: MensalidadeAluno|null,
 *     em_aberto: int,
 *     atrasadas: int,
 *     valor_em_aberto: float,
 *     valor_atrasado: float
 * }
 */
interface ResumoFinanceiroAlunoPort
{
    /**
     * @return ResumoFinanceiroAluno
     */
    public function resumir(int $idAluno): array;
}
