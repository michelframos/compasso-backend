<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\AvaliacaoAluno;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\ObservacaoAluno;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Support\Collection;

class BoletimAlunoQuery
{
    public function __construct(private readonly PlanoEntitlementResolverInterface $entitlements) {}

    /**
     * Nível atual, avaliações e feedback liberado ao responsável, agrupados por matrícula.
     *
     * @return array{matriculas: list<array<string, mixed>>}
     */
    public function montar(int $idAluno, ?int $idMatricula = null): array
    {
        $matriculas = Matricula::query()
            ->where('id_aluno', $idAluno)
            ->with(['turma.curso', 'turma.nivel', 'curso', 'nivel', 'historicos'])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        if ($idMatricula !== null) {
            $matriculas = $matriculas->where('id', $idMatricula)->values();
        }

        $avaliacoes = $this->moduloAtivo('avaliacoes')
            ? AvaliacaoAluno::query()
                ->where('id_aluno', $idAluno)
                ->with(['professor.usuario', 'turma'])
                ->orderByDesc('data')
                ->orderByDesc('id')
                ->get()
            : collect();

        $observacoes = ObservacaoAluno::query()
            ->where('id_aluno', $idAluno)
            ->where('visivel_responsavel', true)
            ->with(['professor.usuario', 'turma'])
            ->latest()
            ->orderByDesc('id')
            ->get();

        $usadasAvaliacoes = collect();
        $usadasObservacoes = collect();

        $grupos = $matriculas
            ->map(function (Matricula $matricula) use ($avaliacoes, $observacoes, &$usadasAvaliacoes, &$usadasObservacoes) {
                $ids = $this->idsTurma($matricula);
                $doGrupoAvaliacoes = $avaliacoes->filter(fn (AvaliacaoAluno $item) => $this->pertence($item->id_turma, $ids, $matricula));
                $doGrupoObservacoes = $observacoes->filter(fn (ObservacaoAluno $item) => $this->pertence($item->id_turma, $ids, $matricula));
                $usadasAvaliacoes = $usadasAvaliacoes->merge($doGrupoAvaliacoes->pluck('id'));
                $usadasObservacoes = $usadasObservacoes->merge($doGrupoObservacoes->pluck('id'));

                return $this->grupo($matricula, $doGrupoAvaliacoes, $doGrupoObservacoes);
            })
            ->values();

        if ($idMatricula === null) {
            $avulsasAvaliacoes = $avaliacoes->reject(fn (AvaliacaoAluno $item) => $usadasAvaliacoes->contains($item->id));
            $avulsasObservacoes = $observacoes->reject(fn (ObservacaoAluno $item) => $usadasObservacoes->contains($item->id));

            if ($avulsasAvaliacoes->isNotEmpty() || $avulsasObservacoes->isNotEmpty()) {
                $grupos->push($this->grupoAvulso($avulsasAvaliacoes, $avulsasObservacoes));
            }
        }

        return ['matriculas' => $grupos->all()];
    }

    /** @return list<int> */
    private function idsTurma(Matricula $matricula): array
    {
        $ids = collect();

        if ($matricula->id_turma) {
            $ids->push((int) $matricula->id_turma);
        }

        foreach ($matricula->historicos as $historico) {
            if ($historico->id_turma_origem) {
                $ids->push((int) $historico->id_turma_origem);
            }
            if ($historico->id_turma_destino) {
                $ids->push((int) $historico->id_turma_destino);
            }
        }

        return $ids->unique()->values()->all();
    }

    private function pertence(?int $idTurma, array $idsTurma, Matricula $matricula): bool
    {
        if ($idTurma === null) {
            return $matricula->tipo === 'curso';
        }

        return in_array((int) $idTurma, $idsTurma, true);
    }

    /**
     * @param  Collection<int, AvaliacaoAluno>  $avaliacoes
     * @param  Collection<int, ObservacaoAluno>  $observacoes
     * @return array<string, mixed>
     */
    private function grupo(Matricula $matricula, Collection $avaliacoes, Collection $observacoes): array
    {
        $nivel = $matricula->tipo === 'curso' ? $matricula->nivel : $matricula->turma?->nivel;
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
            'nivel' => $nivel ? ['id' => $nivel->id, 'nome' => $nivel->nome] : null,
            'avaliacoes' => $this->avaliacoes($avaliacoes),
            'observacoes' => $this->observacoes($observacoes),
        ];
    }

    /**
     * @param  Collection<int, AvaliacaoAluno>  $avaliacoes
     * @param  Collection<int, ObservacaoAluno>  $observacoes
     * @return array<string, mixed>
     */
    private function grupoAvulso(Collection $avaliacoes, Collection $observacoes): array
    {
        return [
            'id_matricula' => null,
            'id_turma' => null,
            'nome' => 'Outros registros',
            'curso' => null,
            'status_matricula' => null,
            'nivel' => null,
            'avaliacoes' => $this->avaliacoes($avaliacoes),
            'observacoes' => $this->observacoes($observacoes),
        ];
    }

    /**
     * @param  Collection<int, AvaliacaoAluno>  $avaliacoes
     * @return list<array<string, mixed>>
     */
    private function avaliacoes(Collection $avaliacoes): array
    {
        return $avaliacoes->values()->map(fn (AvaliacaoAluno $item) => [
            'id' => $item->id,
            'data' => $item->data?->toDateString(),
            'tipo' => $item->tipo,
            'nota' => $item->nota !== null ? (float) $item->nota : null,
            'conceito' => $item->conceito,
            'comentario' => $item->comentario,
            'professor' => $item->professor?->usuario?->nome,
            'turma' => $item->turma?->descricao,
        ])->all();
    }

    /**
     * @param  Collection<int, ObservacaoAluno>  $observacoes
     * @return list<array<string, mixed>>
     */
    private function observacoes(Collection $observacoes): array
    {
        return $observacoes->values()->map(fn (ObservacaoAluno $item) => [
            'id' => $item->id,
            'data' => $item->created_at?->toDateString(),
            'tipo' => $item->tipo,
            'texto' => $item->texto,
            'professor' => $item->professor?->usuario?->nome,
            'turma' => $item->turma?->descricao,
        ])->all();
    }

    private function moduloAtivo(string $modulo): bool
    {
        $instituicao = InstituicaoContext::instituicao();

        return $instituicao !== null && $this->entitlements->permiteModulo($instituicao, $modulo);
    }
}
