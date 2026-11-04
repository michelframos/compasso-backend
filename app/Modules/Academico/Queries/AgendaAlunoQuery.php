<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Core\Contracts\ProximasApresentacoesAlunoPort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AgendaAlunoQuery
{
    public function __construct(private readonly ProximasApresentacoesAlunoPort $apresentacoes) {}

    /**
     * @return array{inicio: string, fim: string, espetaculos: bool, itens: list<array<string, mixed>>}
     */
    public function montar(int $idAluno, string $inicio, string $fim): array
    {
        $aulas = $this->aulas($idAluno, $inicio, $fim);
        $remarcacoes = $this->remarcacoes($aulas);
        $evento = $this->apresentacoes->noPeriodo($idAluno, $inicio, $fim);

        $itens = $aulas
            ->map(fn (AulaTurma $aula) => $this->itemAula($aula, $remarcacoes))
            ->concat(collect($evento['apresentacoes'])->map(fn (array $apresentacao) => [
                'tipo' => 'apresentacao',
                'id' => $apresentacao['id'],
                'data' => $apresentacao['data_evento'],
                'hora_inicio' => null,
                'hora_termino' => null,
                'titulo' => $apresentacao['titulo_musica'],
                'detalhe' => collect([$apresentacao['espetaculo'], $apresentacao['local']])->filter()->implode(' · '),
                'status' => $apresentacao['status'],
                'remarcada' => false,
                'remarcacao' => null,
            ]))
            ->concat(collect($evento['ensaios'])->map(fn (array $ensaio) => [
                'tipo' => 'ensaio',
                'id' => $ensaio['id'],
                'data' => $ensaio['data'],
                'hora_inicio' => $ensaio['hora_inicio'],
                'hora_termino' => $ensaio['hora_termino'],
                'titulo' => 'Ensaio · '.$ensaio['titulo_musica'],
                'detalhe' => collect([$ensaio['espetaculo'], $ensaio['local']])->filter()->implode(' · '),
                'status' => null,
                'remarcada' => false,
                'remarcacao' => null,
            ]))
            ->sortBy(fn (array $item) => $item['data'].' '.($item['hora_inicio'] ?? '99:99').' '.$item['tipo'])
            ->values()
            ->all();

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'espetaculos' => $evento['disponivel'],
            'itens' => $itens,
        ];
    }

    /** @return Collection<int, AulaTurma> */
    private function aulas(int $idAluno, string $inicio, string $fim): Collection
    {
        return $this->restringirAoAluno(AulaTurma::query(), $idAluno)
            ->whereBetween('data', [$inicio, $fim])
            ->with(['turma.curso', 'turma.nivel', 'curso'])
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->get();
    }

    /** @param  Collection<int, AulaTurma>  $aulas
     * @return Collection<int, SolicitacaoAula>
     */
    private function remarcacoes(Collection $aulas): Collection
    {
        $ids = $aulas->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return SolicitacaoAula::query()
            ->where('tipo', SolicitacaoAula::TIPO_REPOSICAO)
            ->where('status', SolicitacaoAula::STATUS_APROVADA)
            ->where(fn (Builder $q) => $q->whereIn('id_aula_turma', $ids)->orWhereIn('id_aula_gerada', $ids))
            ->with(['aula', 'aulaGerada'])
            ->get();
    }

    /** @param  Collection<int, SolicitacaoAula>  $remarcacoes
     * @return array<string, mixed>
     */
    private function itemAula(AulaTurma $aula, Collection $remarcacoes): array
    {
        $comoNova = $remarcacoes->firstWhere('id_aula_gerada', $aula->id);
        $comoOriginal = $remarcacoes->firstWhere('id_aula_turma', $aula->id);
        $remarcada = $comoNova !== null || $comoOriginal !== null;

        return [
            'tipo' => 'aula',
            'id' => $aula->id,
            'data' => $aula->data?->toDateString(),
            'hora_inicio' => $this->hora($aula->hora_inicio),
            'hora_termino' => $this->hora($aula->hora_termino),
            'titulo' => $aula->turma?->apelido() ?? ($aula->curso?->nome ?? 'Aula'),
            'detalhe' => null,
            'status' => $aula->status ?? 'agendada',
            'remarcada' => $remarcada,
            'remarcacao' => $remarcada ? [
                'de' => $comoNova ? $this->marco($comoNova->aula) : null,
                'para' => $comoOriginal ? ($this->marco($comoOriginal->aulaGerada) ?? $this->marcoSugerido($comoOriginal)) : null,
            ] : null,
        ];
    }

    /** @return array{data: string|null, hora_inicio: string, hora_termino: string}|null */
    private function marco(?AulaTurma $aula): ?array
    {
        if ($aula === null) {
            return null;
        }

        return [
            'data' => $aula->data?->toDateString(),
            'hora_inicio' => $this->hora($aula->hora_inicio),
            'hora_termino' => $this->hora($aula->hora_termino),
        ];
    }

    /** @return array{data: string|null, hora_inicio: string, hora_termino: string}|null */
    private function marcoSugerido(SolicitacaoAula $solicitacao): ?array
    {
        if ($solicitacao->data_sugerida === null) {
            return null;
        }

        return [
            'data' => $solicitacao->data_sugerida->toDateString(),
            'hora_inicio' => $this->hora($solicitacao->hora_inicio_sugerida),
            'hora_termino' => $this->hora($solicitacao->hora_termino_sugerida),
        ];
    }

    private function restringirAoAluno(Builder $query, int $idAluno): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('id_aluno_especifico', $idAluno)
            ->orWhere(fn (Builder $turma) => $turma
                ->whereNull('id_aluno_especifico')
                ->whereHas('turma.matriculas', fn (Builder $m) => $m->where('id_aluno', $idAluno)->vigentes())));
    }

    private function hora(mixed $valor): string
    {
        return substr((string) $valor, 0, 5);
    }
}
