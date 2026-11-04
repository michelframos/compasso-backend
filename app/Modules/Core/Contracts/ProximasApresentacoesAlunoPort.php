<?php

namespace App\Modules\Core\Contracts;

/**
 * Integração Academico → Espetaculos (painel e, depois, a agenda do aluno).
 * Sem o módulo no plano, o adapter devolve a lista vazia.
 *
 * @phpstan-type ApresentacaoDoAluno array{
 *     id: int,
 *     titulo_musica: string,
 *     espetaculo: string,
 *     data_evento: string,
 *     local: string|null
 * }
 */
interface ProximasApresentacoesAlunoPort
{
    /**
     * @return array{disponivel: bool, itens: list<ApresentacaoDoAluno>}
     */
    public function listar(int $idAluno, int $limite = 5): array;

    /**
     * Apresentações e ensaios do aluno dentro do período, inclusive os já realizados.
     * Sem o módulo no plano, as duas listas voltam vazias.
     *
     * @return array{
     *     disponivel: bool,
     *     apresentacoes: list<array{id: int, titulo_musica: string, espetaculo: string, data_evento: string, local: string|null, status: string|null}>,
     *     ensaios: list<array{id: int, data: string, hora_inicio: string, hora_termino: string, local: string|null, titulo_musica: string, espetaculo: string}>
     * }
     */
    public function noPeriodo(int $idAluno, string $inicio, string $fim): array;
}
