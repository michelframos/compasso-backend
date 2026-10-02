<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListSugestoesProgressaoQuery
{
    public const FILTROS_STATUS = [...SugestaoProgressao::STATUS, 'todas'];

    /**
     * Pendentes em ordem de chegada (fila); decididas da mais recente para a mais antiga.
     *
     * @param  array<string, mixed>  $filtros  status (pendente|aprovada|rejeitada|todas; padrão pendente), search (nome do aluno)
     */
    public function build(User $user, array $filtros = []): Builder
    {
        $status = $filtros['status'] ?? SugestaoProgressao::STATUS_PENDENTE;

        $query = SugestaoProgressao::query()
            ->visivelPara($user)
            ->with(SugestaoProgressao::DETALHES)
            ->when($status !== 'todas', fn (Builder $q) => $q->where('status', $status));

        if (! empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->whereHas('matricula.aluno.usuario', fn (Builder $u) => $u->where('nome', 'like', "%{$search}%"));
        }

        return $status === SugestaoProgressao::STATUS_PENDENTE
            ? $query->orderBy('created_at')->orderBy('id')
            : $query->orderByRaw('decidido_em IS NULL')->orderByDesc('decidido_em')->orderByDesc('id');
    }
}
