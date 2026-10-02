<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\SolicitacaoAula;
use Illuminate\Database\Eloquent\Builder;

class ListSolicitacoesAulasQuery
{
    public const FILTROS_STATUS = [...SolicitacaoAula::STATUS, 'todas'];

    /**
     * Pendentes em ordem de chegada (fila); as demais da mais recente para a mais antiga.
     *
     * @param  array<string, mixed>  $filtros  status (padrão pendente), tipo, search (nome do professor), id_professor
     */
    public function build(array $filtros = [], string $statusPadrao = SolicitacaoAula::STATUS_PENDENTE): Builder
    {
        $status = $filtros['status'] ?? $statusPadrao;

        $query = SolicitacaoAula::query()
            ->with(SolicitacaoAula::DETALHES)
            ->when($status !== 'todas', fn (Builder $q) => $q->where('status', $status))
            ->when($filtros['tipo'] ?? null, fn (Builder $q, string $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['id_professor'] ?? null, fn (Builder $q, int $id) => $q->where('id_professor', $id));

        if (! empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->whereHas('professor.usuario', fn (Builder $u) => $u->where('nome', 'like', "%{$search}%"));
        }

        return $status === SolicitacaoAula::STATUS_PENDENTE
            ? $query->orderBy('created_at')->orderBy('id')
            : $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
