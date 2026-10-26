<?php

namespace App\Modules\Espetaculos\Queries;

use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\Espetaculo;
use Illuminate\Database\Eloquent\Builder;

class ListApresentacoesDoAlunoQuery
{
    /** Só as apresentações em que o aluno está escalado, da mais próxima à mais distante. */
    public function build(int $idAluno): Builder
    {
        $dataDoEspetaculo = Espetaculo::query()
            ->select('data_evento')
            ->whereColumn('espetaculos.id', 'apresentacoes.id_espetaculo');

        return Apresentacao::query()
            ->whereHas('alunos', fn (Builder $q) => $q->where('id_aluno', $idAluno))
            ->with([
                'espetaculo',
                'turma',
                'alunos' => fn ($q) => $q->where('id_aluno', $idAluno),
                'ensaios' => fn ($q) => $q->orderBy('data')->orderBy('hora_inicio'),
            ])
            ->orderBy($dataDoEspetaculo)
            ->orderBy('ordem_entrada')
            ->orderBy('id');
    }
}
