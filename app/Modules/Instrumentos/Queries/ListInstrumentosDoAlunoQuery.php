<?php

namespace App\Modules\Instrumentos\Queries;

use App\Modules\Instrumentos\Models\Instrumento;
use App\Modules\Instrumentos\Models\InstrumentoHistorico;
use Illuminate\Database\Eloquent\Collection;

class ListInstrumentosDoAlunoQuery
{
    /** @return array{atuais: Collection<int, Instrumento>, historico: Collection<int, InstrumentoHistorico>} */
    public function build(int $idAluno): array
    {
        return [
            'atuais' => Instrumento::query()
                ->where('id_aluno', $idAluno)
                ->orderBy('nome')
                ->orderBy('id')
                ->get(),
            'historico' => InstrumentoHistorico::query()
                ->with('instrumento:id,nome,tipo')
                ->where('id_aluno', $idAluno)
                ->orderByDesc('data')
                ->orderByDesc('id')
                ->get(),
        ];
    }
}
