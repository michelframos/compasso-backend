<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\Matricula;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FrequenciaAlunoQuery
{
    /**
     * Presenças lançadas do aluno, agrupadas pela matrícula (turma atual e turmas
     * anteriores da mesma matrícula). Aula cancelada não entra. Aula individual de
     * outro aluno também não, mesmo que exista uma chamada neste nome.
     *
     * @return array{resumo: array<string, int|float|null>, turmas: list<array<string, mixed>>}
     */
    public function montar(int $idAluno, ?int $idMatricula = null): array
    {
        $matriculas = Matricula::query()
            ->where('id_aluno', $idAluno)
            ->with(['turma.curso', 'turma.nivel', 'curso', 'historicos'])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        if ($idMatricula !== null) {
            $matriculas = $matriculas->where('id', $idMatricula)->values();
        }

        $porMatricula = $matriculas->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => collect()]);
        $avulsas = collect();

        foreach ($this->presencas($idAluno) as $presenca) {
            $matricula = $this->matriculaDaPresenca($presenca, $matriculas, $idAluno);

            if ($matricula !== null) {
                $porMatricula[$matricula->id]->push($presenca);

                continue;
            }

            if ($idMatricula === null && $this->aulaIndividual($presenca->aula, $idAluno)) {
                $avulsas->push($presenca);
            }
        }

        $contadas = collect();
        $turmas = $matriculas
            ->map(function (Matricula $matricula) use ($porMatricula, &$contadas) {
                $lista = $porMatricula[$matricula->id];
                $contadas = $contadas->concat($lista);

                return $this->grupo($matricula, $lista);
            })
            ->values();

        if ($avulsas->isNotEmpty()) {
            $contadas = $contadas->concat($avulsas);
            $turmas->push($this->grupoAvulso($avulsas));
        }

        return [
            'resumo' => $this->resumo($contadas),
            'turmas' => $turmas->all(),
        ];
    }

    /** @return Collection<int, AulaPresenca> */
    private function presencas(int $idAluno): Collection
    {
        return AulaPresenca::query()
            ->where('id_aluno', $idAluno)
            ->whereHas('aula', fn (Builder $aula) => $aula
                ->where(fn (Builder $status) => $status
                    ->whereNull('status')
                    ->orWhere('status', '!=', 'cancelada'))
                ->where(fn (Builder $aluno) => $aluno
                    ->whereNull('id_aluno_especifico')
                    ->orWhere('id_aluno_especifico', $idAluno)))
            ->with(['aula.turma.curso', 'aula.turma.nivel', 'aula.curso'])
            ->get();
    }

    /** @param  Collection<int, Matricula>  $matriculas */
    private function matriculaDaPresenca(AulaPresenca $presenca, Collection $matriculas, int $idAluno): ?Matricula
    {
        $aula = $presenca->aula;

        if ($aula->id_turma) {
            return $matriculas
                ->filter(fn (Matricula $matricula) => $this->turmaDaMatricula($matricula, (int) $aula->id_turma))
                ->sortByDesc(fn (Matricula $matricula) => sprintf(
                    '%d-%s-%010d',
                    (int) $matricula->id_turma === (int) $aula->id_turma ? 1 : 0,
                    $matricula->data?->toDateString() ?? '',
                    $matricula->id,
                ))
                ->first();
        }

        if (! $this->aulaIndividual($aula, $idAluno)) {
            return null;
        }

        return $matriculas
            ->where('tipo', 'curso')
            ->when($aula->id_curso, fn (Collection $cursos) => $cursos->where('id_curso', $aula->id_curso))
            ->sortByDesc('id')
            ->first();
    }

    private function turmaDaMatricula(Matricula $matricula, int $idTurma): bool
    {
        if ((int) $matricula->id_turma === $idTurma) {
            return true;
        }

        return $matricula->historicos->contains(fn ($historico) => (int) $historico->id_turma_origem === $idTurma
            || (int) $historico->id_turma_destino === $idTurma);
    }

    private function aulaIndividual(AulaTurma $aula, int $idAluno): bool
    {
        return $aula->id_turma === null && (int) $aula->id_aluno_especifico === $idAluno;
    }

    /**
     * @param  Collection<int, AulaPresenca>  $presencas
     * @return array<string, mixed>
     */
    private function grupo(Matricula $matricula, Collection $presencas): array
    {
        $nome = $matricula->tipo === 'curso'
            ? ($matricula->curso?->nome ?: 'Aulas individuais')
            : ($matricula->turma?->apelido() ?? 'Turma');

        $curso = $matricula->tipo === 'curso'
            ? $matricula->curso?->nome
            : $matricula->turma?->curso?->nome;

        return [
            'id_matricula' => $matricula->id,
            'id_turma' => $matricula->id_turma,
            'nome' => $nome,
            'curso' => $curso,
            'status_matricula' => $matricula->status,
            ...$this->resumo($presencas),
            'aulas' => $this->aulas($presencas),
        ];
    }

    /**
     * @param  Collection<int, AulaPresenca>  $presencas
     * @return array<string, mixed>
     */
    private function grupoAvulso(Collection $presencas): array
    {
        return [
            'id_matricula' => null,
            'id_turma' => null,
            'nome' => 'Aulas individuais',
            'curso' => null,
            'status_matricula' => null,
            ...$this->resumo($presencas),
            'aulas' => $this->aulas($presencas),
        ];
    }

    /**
     * @param  Collection<int, AulaPresenca>  $presencas
     * @return list<array<string, mixed>>
     */
    private function aulas(Collection $presencas): array
    {
        return $presencas
            ->sortByDesc(fn (AulaPresenca $presenca) => $presenca->aula->data?->toDateString().' '.$presenca->aula->hora_inicio)
            ->values()
            ->map(fn (AulaPresenca $presenca) => [
                'id' => $presenca->id,
                'id_aula' => $presenca->id_aula_turma,
                'data' => $presenca->aula->data?->toDateString(),
                'hora_inicio' => $this->hora($presenca->aula->hora_inicio),
                'hora_termino' => $this->hora($presenca->aula->hora_termino),
                'status' => $presenca->status,
            ])
            ->all();
    }

    /**
     * O percentual conta só `presente`. Falta justificada entra no total, mas não como presença.
     *
     * @param  Collection<int, AulaPresenca>  $presencas
     * @return array{total: int, presentes: int, ausentes: int, justificados: int, percentual: float|null}
     */
    private function resumo(Collection $presencas): array
    {
        $total = $presencas->count();
        $presentes = $presencas->where('status', 'presente')->count();

        return [
            'total' => $total,
            'presentes' => $presentes,
            'ausentes' => $presencas->where('status', 'ausente')->count(),
            'justificados' => $presencas->where('status', 'justificado')->count(),
            'percentual' => $total > 0 ? round($presentes / $total * 100, 1) : null,
        ];
    }

    private function hora(mixed $valor): string
    {
        return substr((string) $valor, 0, 5);
    }
}
