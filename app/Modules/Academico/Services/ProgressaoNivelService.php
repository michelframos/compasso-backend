<?php

namespace App\Modules\Academico\Services;

use App\Enums\TurmaStatus;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Academico\Models\Turma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProgressaoNivelService
{
    private const STATUS_SEM_VAGAS = [TurmaStatus::CONCLUIDA->value, TurmaStatus::CANCELADA->value];

    /**
     * Turmas que podem receber o aluno de uma matrícula em turma: mesmo curso, nível sugerido e não encerradas.
     *
     * @return Collection<int, Turma>
     */
    public function turmasDestino(SugestaoProgressao $sugestao): Collection
    {
        $matricula = $sugestao->matricula;

        if (! $matricula || $matricula->tipo !== 'turma') {
            return collect();
        }

        return Turma::query()
            ->where('id_curso', $matricula->idCursoAtual())
            ->where('id_nivel', $sugestao->id_nivel_sugerido)
            ->whereNotIn('status', self::STATUS_SEM_VAGAS)
            ->whereKeyNot($matricula->id_turma)
            ->with(['curso', 'nivel', 'professor.usuario'])
            ->withCount(['matriculas as alunos_vigentes_count' => fn (Builder $q) => $q->vigentes()])
            ->orderBy('id')
            ->get();
    }

    /** Motivo de recusa da turma de destino, ou null quando ela pode receber o aluno. */
    public function impedimentoDaTurmaDestino(Turma $turma, Matricula $matricula, int $idNivelSugerido): ?string
    {
        $status = $turma->status instanceof TurmaStatus ? $turma->status->value : $turma->status;

        return match (true) {
            $turma->id === $matricula->id_turma => 'O aluno já está nesta turma.',
            (int) $turma->id_nivel !== $idNivelSugerido => 'A turma de destino precisa ser do nível sugerido.',
            (int) $turma->id_curso !== (int) $matricula->idCursoAtual() => 'A turma de destino precisa ser do mesmo curso da matrícula.',
            in_array($status, self::STATUS_SEM_VAGAS, true) => 'Não é possível transferir para uma turma concluída ou cancelada.',
            $turma->matriculas()->vigentes()->count() >= $turma->maximo_alunos => 'A turma de destino já atingiu a capacidade máxima de alunos.',
            default => null,
        };
    }
}
