<?php

namespace App\Modules\Academico\Queries;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Support\RegrasReposicaoAluno;
use Illuminate\Support\Collection;

class OpcoesReposicaoQuery
{
    public function __construct(private readonly RegrasReposicaoAluno $regras) {}

    /** @return Collection<int, AulaTurma> */
    public function listar(int $idAluno, AulaTurma $perdida): Collection
    {
        $perdida->loadMissing('turma');
        $cursoId = $perdida->id_curso ?: $perdida->turma?->id_curso;

        if ($cursoId === null) {
            return collect();
        }

        return AulaTurma::query()
            ->with('turma')
            ->where('id_curso', $cursoId)
            ->where('status', 'agendada')
            ->whereDate('data', '>=', now()->toDateString())
            ->whereDate('data', '<=', now()->addDays(45)->toDateString())
            ->whereKeyNot($perdida->id)
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->get()
            ->filter(fn (AulaTurma $aula) => $this->regras->erroDoDestino($idAluno, $perdida, $aula) === null)
            ->values();
    }
}
