<?php

namespace App\Modules\Financeiro\Queries;

use App\Modules\Financeiro\Queries\Situacao\Atrasadas;
use App\Modules\Financeiro\Queries\Situacao\AVencer;
use App\Modules\Financeiro\Queries\Situacao\Pagas;
use App\Modules\Financeiro\Queries\Situacao\SituacaoContaFiltro;
use App\Modules\Financeiro\Queries\Situacao\VencendoHoje;
use Illuminate\Database\Eloquent\Builder;

class ContaQuery
{
    /** @var array<string, class-string<SituacaoContaFiltro>> */
    private const SITUACOES = [
        'a_vencer' => AVencer::class,
        'vencendo_hoje' => VencendoHoje::class,
        'atrasadas' => Atrasadas::class,
        'pagas' => Pagas::class,
    ];

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function aplicar(Builder $query, array $filtros): Builder
    {
        $situacao = $filtros['situacao'] ?? null;
        if ($situacao && isset(self::SITUACOES[$situacao])) {
            (new (self::SITUACOES[$situacao]))->aplicar($query, now()->toDateString());
        }

        foreach (['status', 'tipo', 'id_aluno', 'id_matricula'] as $coluna) {
            if (! empty($filtros[$coluna])) {
                $query->where($coluna, $filtros[$coluna]);
            }
        }

        if (! empty($filtros['id_turma'])) {
            $query->whereHas('matricula', fn (Builder $q) => $q->where('id_turma', $filtros['id_turma']));
        }

        if (! empty($filtros['id_curso'])) {
            $query->whereHas('matricula.turma', fn (Builder $q) => $q->where('id_curso', $filtros['id_curso']));
        }

        if (! empty($filtros['data_inicio'])) {
            $query->whereDate('data_vencimento', '>=', $filtros['data_inicio']);
        }

        if (! empty($filtros['data_fim'])) {
            $query->whereDate('data_vencimento', '<=', $filtros['data_fim']);
        }

        if (! empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('descricao', 'like', "%{$search}%")
                    ->orWhereHas('aluno.usuario', fn (Builder $q2) => $q2->where('nome', 'like', "%{$search}%"));
            });
        }

        return $query;
    }
}
