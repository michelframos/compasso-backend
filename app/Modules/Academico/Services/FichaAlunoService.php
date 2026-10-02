<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AvaliacaoAluno;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\ObservacaoAluno;
use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ficha do aluno restrita ao que o usuário pode ver: para o professor, apenas matrículas,
 * presenças e observações ligadas às suas turmas/aulas.
 */
class FichaAlunoService
{
    private const ULTIMAS_PRESENCAS = 10;

    public function __construct(private readonly PlanoEntitlementResolverInterface $entitlements)
    {
    }

    private function moduloAtivo(string $modulo): bool
    {
        $instituicao = InstituicaoContext::instituicao();

        return $instituicao !== null && $this->entitlements->permiteModulo($instituicao, $modulo);
    }

    /**
     * @return array{aluno: Aluno, matriculas: Collection, frequencia: array, observacoes: Collection, avaliacoes: Collection, progressoes: Collection}
     */
    public function montar(Aluno $aluno, User $user): array
    {
        $aluno->loadMissing(['usuario', 'responsaveis.usuario']);

        $matriculas = Matricula::query()
            ->visivelPara($user)
            ->where('id_aluno', $aluno->id)
            ->with(['turma.curso', 'turma.nivel', 'curso', 'nivel'])
            ->orderByDesc('data')
            ->get();

        $presencas = AulaPresenca::query()
            ->where('id_aluno', $aluno->id)
            ->whereHas('aula', fn (Builder $q) => $q->visivelPara($user)->where('status', '!=', 'cancelada'))
            ->with('aula:id,id_turma,data,hora_inicio')
            ->get()
            ->sortByDesc(fn (AulaPresenca $p) => $p->aula->data->format('Y-m-d').' '.$p->aula->hora_inicio)
            ->values();

        $observacoes = ObservacaoAluno::query()
            ->visivelPara($user)
            ->where('id_aluno', $aluno->id)
            ->with(ObservacaoAluno::DETALHES)
            ->latest()
            ->orderByDesc('id')
            ->get();

        $avaliacoes = $this->moduloAtivo('avaliacoes')
            ? AvaliacaoAluno::query()
                ->visivelPara($user)
                ->where('id_aluno', $aluno->id)
                ->with(AvaliacaoAluno::DETALHES)
                ->orderByDesc('data')
                ->orderByDesc('id')
                ->get()
            : collect();

        $progressoes = $this->moduloAtivo('progressao')
            ? SugestaoProgressao::query()
                ->visivelPara($user)
                ->whereIn('id_matricula', $matriculas->pluck('id'))
                ->with(SugestaoProgressao::DETALHES)
                ->latest()
                ->orderByDesc('id')
                ->get()
            : collect();

        return [
            'aluno' => $aluno,
            'matriculas' => $matriculas,
            'frequencia' => $this->frequencia($presencas, $matriculas->pluck('id_turma')->filter()->unique()->values()),
            'observacoes' => $observacoes,
            'avaliacoes' => $avaliacoes,
            'progressoes' => $progressoes,
        ];
    }

    /**
     * Em `por_turma`, o grupo com `id_turma` nulo reúne as aulas individuais (sem turma).
     *
     * @param  Collection<int, AulaPresenca>  $presencas  ordenadas da mais recente para a mais antiga
     * @param  Collection<int, int>  $turmas
     */
    private function frequencia(Collection $presencas, Collection $turmas): array
    {
        $grupos = $turmas->map(fn ($idTurma) => (int) $idTurma);

        if ($presencas->contains(fn (AulaPresenca $p) => $p->aula->id_turma === null)) {
            $grupos->push(null);
        }

        return [
            ...$this->resumo($presencas),
            'por_turma' => $grupos
                ->map(fn (?int $idTurma) => [
                    'id_turma' => $idTurma,
                    ...$this->resumo($presencas->filter(fn (AulaPresenca $p) => $p->aula->id_turma === null
                        ? $idTurma === null
                        : (int) $p->aula->id_turma === $idTurma)),
                ])
                ->values()
                ->all(),
            'ultimas' => $presencas
                ->take(self::ULTIMAS_PRESENCAS)
                ->map(fn (AulaPresenca $p) => [
                    'id_aula' => $p->id_aula_turma,
                    'id_turma' => $p->aula->id_turma,
                    'data' => $p->aula->data->format('Y-m-d'),
                    'status' => $p->status,
                    'observacao' => $p->observacao,
                ])
                ->all(),
        ];
    }

    /** @param  Collection<int, AulaPresenca>  $presencas */
    private function resumo(Collection $presencas): array
    {
        $total = $presencas->count();
        $presentes = $presencas->where('status', 'presente')->count();

        return [
            'total' => $total,
            'presentes' => $presentes,
            'ausentes' => $presencas->where('status', 'ausente')->count(),
            'justificados' => $presencas->where('status', 'justificado')->count(),
            'percentual_presenca' => $total > 0 ? round($presentes / $total * 100, 1) : null,
        ];
    }
}
