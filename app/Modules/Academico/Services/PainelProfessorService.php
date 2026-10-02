<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Queries\ListTurmasDoProfessorQuery;
use App\Modules\Core\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PainelProfessorService
{
    public const LIMITE_CHAMADAS_PENDENTES = 20;

    /**
     * @return array{
     *     data_referencia: string,
     *     inicio_semana: string,
     *     fim_semana: string,
     *     contadores: array<string, int>,
     *     aulas_hoje: Collection<int, AulaTurma>,
     *     aulas_semana: Collection<int, AulaTurma>,
     *     chamadas_pendentes: Collection<int, AulaTurma>
     * }
     */
    public function montar(User $user, ?CarbonInterface $agora = null): array
    {
        $agora = CarbonImmutable::instance($agora ?? now());
        $hoje = $agora->toDateString();
        $inicioSemana = $agora->startOfWeek(CarbonInterface::MONDAY)->toDateString();
        $fimSemana = $agora->endOfWeek(CarbonInterface::SUNDAY)->toDateString();

        $aulasSemana = $this->aulas($user)
            ->whereBetween('data', [$inicioSemana, $fimSemana])
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->get();
        $aulasHoje = $aulasSemana->filter(fn (AulaTurma $aula) => $aula->data->toDateString() === $hoje)->values();

        $pendentes = $this->chamadasPendentes($user, $agora);

        return [
            'data_referencia' => $hoje,
            'inicio_semana' => $inicioSemana,
            'fim_semana' => $fimSemana,
            'contadores' => [
                'aulas_hoje' => $this->naoCanceladas($aulasHoje),
                'aulas_semana' => $this->naoCanceladas($aulasSemana),
                'chamadas_pendentes' => (clone $pendentes)->count(),
                'turmas_ativas' => $this->turmasAtivas($user)->count(),
                'alunos_ativos' => $this->alunosAtivos($user),
            ],
            'aulas_hoje' => $aulasHoje,
            'aulas_semana' => $aulasSemana,
            'chamadas_pendentes' => $pendentes
                ->orderByDesc('data')
                ->orderByDesc('hora_inicio')
                ->limit(self::LIMITE_CHAMADAS_PENDENTES)
                ->get(),
        ];
    }

    /** Aulas já encerradas e não canceladas cuja chamada não foi feita (status agendada ou sem presenças). */
    private function chamadasPendentes(User $user, CarbonImmutable $agora): Builder
    {
        $hoje = $agora->toDateString();

        return $this->aulas($user)
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelada'))
            ->where(fn (Builder $q) => $q
                ->where('data', '<', $hoje)
                ->orWhere(fn (Builder $h) => $h->where('data', $hoje)->where('hora_termino', '<=', $agora->format('H:i:s'))))
            ->where(fn (Builder $q) => $q
                ->whereNull('status')
                ->orWhere('status', 'agendada')
                ->orWhereDoesntHave('presencas'));
    }

    private function aulas(User $user): Builder
    {
        return AulaTurma::query()
            ->visivelPara($user)
            ->with([
                'turma.curso',
                'turma.nivel',
                'turma.matriculas' => fn ($q) => $q->where('status', 'ativa')->with('aluno.usuario'),
                'aluno_especifico.usuario',
            ])
            ->withCount('presencas');
    }

    private function turmasAtivas(User $user): Builder
    {
        return Turma::query()
            ->visivelPara($user)
            ->whereNotIn('status', ListTurmasDoProfessorQuery::STATUS_ENCERRADOS);
    }

    private function alunosAtivos(User $user): int
    {
        $turmas = $this->turmasAtivas($user)->select('turmas.id');

        return Matricula::query()
            ->where('status', 'ativa')
            ->whereIn('id_turma', $turmas)
            ->distinct()
            ->count('id_aluno');
    }

    /** @param  Collection<int, AulaTurma>  $aulas */
    private function naoCanceladas(Collection $aulas): int
    {
        return $aulas->filter(fn (AulaTurma $aula) => $aula->status !== 'cancelada')->count();
    }
}
