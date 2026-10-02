<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\Instituicao;

/**
 * O que o professor pode fazer com as aulas na escola, por ação:
 * livre (faz direto), aprovacao (vira solicitação para a secretaria) ou bloqueado.
 * Sem configuração, tudo é livre (comportamento anterior à fase de solicitações).
 */
final class PermissoesProfessorAulas
{
    public const CRIAR = 'criar';

    public const EDITAR = 'editar';

    public const EXCLUIR = 'excluir';

    public const ACOES = [self::CRIAR, self::EDITAR, self::EXCLUIR];

    public const LIVRE = 'livre';

    public const APROVACAO = 'aprovacao';

    public const BLOQUEADO = 'bloqueado';

    public const MODOS = [self::LIVRE, self::APROVACAO, self::BLOQUEADO];

    /** @return array{criar: string, editar: string, excluir: string} */
    public static function normalizar(?array $configuradas): array
    {
        $permissoes = [];

        foreach (self::ACOES as $acao) {
            $modo = $configuradas[$acao] ?? null;
            $permissoes[$acao] = in_array($modo, self::MODOS, true) ? $modo : self::LIVRE;
        }

        return $permissoes;
    }

    /** @return array{criar: string, editar: string, excluir: string} */
    public static function da(?Instituicao $instituicao): array
    {
        return self::normalizar($instituicao?->permissoes_professor_aulas);
    }

    public static function modo(string $acao): string
    {
        return self::da(InstituicaoContext::instituicao())[$acao];
    }
}
