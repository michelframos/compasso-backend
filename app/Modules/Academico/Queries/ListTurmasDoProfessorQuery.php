<?php

namespace App\Modules\Academico\Queries;

use App\Enums\TurmaStatus;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListTurmasDoProfessorQuery
{
    public const SITUACOES = ['ativas', 'encerradas', 'todas'];

    public const STATUS_ENCERRADOS = [TurmaStatus::CONCLUIDA->value, TurmaStatus::CANCELADA->value];

    /**
     * @param  array<string, mixed>  $filtros  situacao (ativas|encerradas|todas), search
     */
    public function build(User $user, array $filtros = []): Builder
    {
        $query = Turma::query()
            ->visivelPara($user)
            ->with(['curso', 'nivel', 'horarios' => fn ($q) => $q->orderBy('hora_inicio')])
            ->withCount(['matriculas as alunos_ativos_count' => fn (Builder $q) => $q->where('status', 'ativa')]);

        match ($filtros['situacao'] ?? 'ativas') {
            'encerradas' => $query->whereIn('status', self::STATUS_ENCERRADOS),
            'todas' => $query,
            default => $query->whereNotIn('status', self::STATUS_ENCERRADOS),
        };

        if (! empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(fn (Builder $q) => $q
                ->where('descricao', 'like', "%{$search}%")
                ->orWhereHas('curso', fn (Builder $c) => $c->where('nome', 'like', "%{$search}%"))
                ->orWhereHas('nivel', fn (Builder $n) => $n->where('nome', 'like', "%{$search}%")));
        }

        return $query->orderBy('turmas.id', 'desc');
    }
}
