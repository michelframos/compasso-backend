<?php

namespace App\Modules\Relatorios\Services;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Frequência e absenteísmo por aluno no período, restrita às aulas que o usuário pode ver
 * (o professor enxerga só as próprias aulas; secretaria e admin, todas da instituição).
 * Usado pelo relatório da secretaria e pela área do professor.
 */
class AbsenteismoService
{
    /**
     * Faltas consecutivas contam só `ausente`, das aulas mais recentes até o fim do período (ou hoje,
     * se o período ainda não acabou), dentro do mesmo escopo de aulas; qualquer outro status interrompe a sequência.
     *
     * @return array{data: Collection, summary: array, filters: array}
     */
    public function calcular(User $user, string $dataInicio, string $dataFim, int $limiteFaltas, ?int $idTurma = null): array
    {
        $aulasDoPeriodo = $this->aulas($user, $idTurma)
            ->whereBetween('data', [$dataInicio, $dataFim])
            ->select('id');

        $alunos = DB::table('aulas_presencas')
            ->join('alunos', 'aulas_presencas.id_aluno', '=', 'alunos.id')
            ->join('usuarios', 'alunos.id_usuario', '=', 'usuarios.id')
            ->whereNull('aulas_presencas.deleted_at')
            ->whereIn('aulas_presencas.id_aula_turma', $aulasDoPeriodo)
            ->select(
                'alunos.id',
                'usuarios.nome as nome_aluno',
                DB::raw("SUM(CASE WHEN aulas_presencas.status = 'presente' THEN 1 ELSE 0 END) as total_presencas"),
                DB::raw("SUM(CASE WHEN aulas_presencas.status IN ('ausente', 'justificado') THEN 1 ELSE 0 END) as total_faltas"),
                DB::raw("SUM(CASE WHEN aulas_presencas.status = 'justificado' THEN 1 ELSE 0 END) as total_justificadas"),
                DB::raw('COUNT(aulas_presencas.id) as total_aulas')
            )
            ->groupBy('alunos.id', 'usuarios.nome')
            ->orderBy('usuarios.nome')
            ->get();

        $consecutivas = $this->faltasConsecutivas($user, $idTurma, $alunos->pluck('id'), min($dataFim, now()->toDateString()));

        $dados = $alunos->map(function (object $linha) use ($consecutivas, $limiteFaltas): array {
            $totalAulas = (int) $linha->total_aulas;
            $totalFaltas = (int) $linha->total_faltas;
            $faltasConsecutivas = $consecutivas[$linha->id] ?? 0;

            return [
                'id' => (int) $linha->id,
                'nome_aluno' => $linha->nome_aluno,
                'total_presencas' => (int) $linha->total_presencas,
                'total_faltas' => $totalFaltas,
                'total_justificadas' => (int) $linha->total_justificadas,
                'total_aulas' => $totalAulas,
                'taxa_absenteismo' => $totalAulas > 0 ? round($totalFaltas / $totalAulas * 100, 2) : 0,
                'faltas_consecutivas' => $faltasConsecutivas,
                'em_risco' => $faltasConsecutivas >= $limiteFaltas,
            ];
        })->values();

        return [
            'data' => $dados,
            'summary' => [
                'total_alunos' => $dados->count(),
                'media_absenteismo' => round($dados->avg('taxa_absenteismo') ?? 0, 2),
                'alunos_em_risco' => $dados->where('em_risco', true)->count(),
            ],
            'filters' => [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'id_turma' => $idTurma,
                'limite_faltas' => $limiteFaltas,
            ],
        ];
    }

    private function aulas(User $user, ?int $idTurma): Builder
    {
        return AulaTurma::query()
            ->visivelPara($user)
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelada'))
            ->when($idTurma, fn (Builder $q) => $q->where('id_turma', $idTurma));
    }

    /**
     * @param  Collection<int, int>  $idsAlunos
     * @return array<int, int> id_aluno => faltas consecutivas
     */
    private function faltasConsecutivas(User $user, ?int $idTurma, Collection $idsAlunos, string $ate): array
    {
        if ($idsAlunos->isEmpty()) {
            return [];
        }

        return DB::table('aulas_presencas')
            ->join('aulas_turmas', 'aulas_presencas.id_aula_turma', '=', 'aulas_turmas.id')
            ->whereNull('aulas_presencas.deleted_at')
            ->whereIn('aulas_presencas.id_aluno', $idsAlunos)
            ->whereIn('aulas_presencas.id_aula_turma', $this->aulas($user, $idTurma)->where('data', '<=', $ate)->select('id'))
            ->orderByDesc('aulas_turmas.data')
            ->orderByDesc('aulas_turmas.hora_inicio')
            ->orderByDesc('aulas_turmas.id')
            ->get(['aulas_presencas.id_aluno', 'aulas_presencas.status'])
            ->groupBy('id_aluno')
            ->map(function (Collection $presencas): int {
                $sequencia = 0;
                foreach ($presencas as $presenca) {
                    if ($presenca->status !== 'ausente') {
                        break;
                    }
                    $sequencia++;
                }

                return $sequencia;
            })
            ->all();
    }
}
