<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\Instituicao;

/**
 * Contexto request-scoped da instituição (tenant) ativa.
 *
 * Populado pelo middleware ResolveInstituicao (Fase 7) ou manualmente em jobs/commands.
 */
class InstituicaoContext
{
    private static ?int $id = null;

    private static ?string $slug = null;

    private static ?Instituicao $instituicao = null;

    public static function set(?int $id, ?string $slug = null, ?Instituicao $instituicao = null): void
    {
        self::$id = $id;
        self::$slug = $slug ?? $instituicao?->slug;
        self::$instituicao = $instituicao;
    }

    public static function setFromModel(Instituicao $instituicao): void
    {
        self::set($instituicao->id, $instituicao->slug, $instituicao);
    }

    public static function id(): ?int
    {
        return self::$id;
    }

    public static function slug(): ?string
    {
        return self::$slug;
    }

    public static function instituicao(): ?Instituicao
    {
        return self::$instituicao;
    }

    public static function has(): bool
    {
        return self::$id !== null;
    }

    public static function clear(): void
    {
        self::$id = null;
        self::$slug = null;
        self::$instituicao = null;
    }

    /**
     * Regra exists com filtro pela instituição ativa (quando houver contexto).
     */
    public static function existsRule(string $table, string $column = 'id'): \Illuminate\Validation\Rules\Exists
    {
        $rule = \Illuminate\Validation\Rule::exists($table, $column);

        if (self::has()) {
            $rule->where('id_instituicao', self::id());
        }

        return $rule;
    }

    /**
     * Aplica filtro id_instituicao em query builder (relatórios/dashboard com DB::table).
     */
    public static function applyToQuery(\Illuminate\Database\Query\Builder $query, string $table): void
    {
        if (self::has()) {
            $query->where("{$table}.id_instituicao", self::id());
        }
    }

    /**
     * Atributos de pivot com id_instituicao do contexto ativo.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function pivotAttributes(array $attributes = []): array
    {
        if (self::has()) {
            $attributes['id_instituicao'] = self::id();
        }

        return $attributes;
    }

    /**
     * Executa um callback com contexto temporário (útil em jobs/queues).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function runWith(int $idInstituicao, ?string $slug, callable $callback): mixed
    {
        $previousId = self::$id;
        $previousSlug = self::$slug;
        $previousInstituicao = self::$instituicao;

        self::set($idInstituicao, $slug);

        try {
            return $callback();
        } finally {
            self::set($previousId, $previousSlug, $previousInstituicao);
        }
    }
}
