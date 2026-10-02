<?php

namespace App\Modules\Core\Contracts;

/**
 * Cobrança avulsa vinculada a uma aula (conta a receber com id_aula_turma).
 * Contas canceladas são ignoradas.
 */
interface CobrancaAulaPort
{
    /**
     * @param  list<int>  $idsAulas
     * @return list<int> aulas, entre as informadas, que têm cobrança ativa
     */
    public function aulasComCobranca(array $idsAulas): array;

    /** Move a cobrança para outra aula, mantendo valor, vencimento e pagamentos. */
    public function transferir(int $idAulaOrigem, int $idAulaDestino, string $observacao): void;

    /** Cancela a cobrança; false se ela já recebeu pagamento. */
    public function cancelar(int $idAula, string $observacao): bool;
}
