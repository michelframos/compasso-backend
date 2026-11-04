<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Support\ElegibilidadeCertificado;
use Illuminate\Support\Collection;

class ListarMatriculasElegiveisCertificado
{
    public function __construct(private readonly ElegibilidadeCertificado $elegibilidade) {}

    /**
     * Matrículas completadas, sem certificado, com frequência mínima.
     *
     * @return Collection<int, array{
     *   id: int,
     *   aluno: array{id: int, nome: string|null}|null,
     *   curso: string|null,
     *   turma: string|null,
     *   nivel: string|null,
     *   frequencia_percentual: float,
     *   frequencia_minima: float,
     *   carga_horaria: float
     * }>
     */
    public function execute(): Collection
    {
        $emitidas = Certificado::query()->pluck('id_matricula')->all();

        $matriculas = Matricula::query()
            ->where('status', Certificado::STATUS_MATRICULA)
            ->when($emitidas !== [], fn ($q) => $q->whereNotIn('id', $emitidas))
            ->with(['aluno.usuario', 'turma.curso', 'turma.nivel', 'curso', 'nivel', 'historicos'])
            ->orderByDesc('id')
            ->get();

        return $matriculas
            ->map(function (Matricula $matricula) {
                $preview = $this->elegibilidade->preview($matricula);
                if ($preview === null) {
                    return null;
                }

                return [
                    'id' => $matricula->id,
                    'aluno' => $matricula->aluno ? [
                        'id' => $matricula->aluno->id,
                        'nome' => $matricula->aluno->usuario?->nome,
                    ] : null,
                    'curso' => $matricula->tipo === 'curso'
                        ? $matricula->curso?->nome
                        : $matricula->turma?->curso?->nome,
                    'turma' => $matricula->tipo === 'turma'
                        ? ($matricula->turma?->apelido() ?? $matricula->turma?->descricao)
                        : null,
                    'nivel' => $matricula->tipo === 'curso'
                        ? $matricula->nivel?->nome
                        : $matricula->turma?->nivel?->nome,
                    'frequencia_percentual' => $preview['percentual'],
                    'frequencia_minima' => $preview['frequencia_minima'],
                    'carga_horaria' => $preview['carga_horaria'],
                ];
            })
            ->filter()
            ->values();
    }
}
