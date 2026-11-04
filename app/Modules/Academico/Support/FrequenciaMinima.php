<?php

namespace App\Modules\Academico\Support;

use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;

class FrequenciaMinima
{
    public static function resolver(?Turma $turma = null, ?Instituicao $instituicao = null): float
    {
        if ($turma !== null && $turma->frequencia_minima !== null) {
            return (float) $turma->frequencia_minima;
        }

        $escola = $instituicao ?? self::instituicaoDaTurma($turma) ?? InstituicaoContext::instituicao();

        if ($escola !== null && $escola->frequencia_minima !== null) {
            return (float) $escola->frequencia_minima;
        }

        return Certificado::FREQUENCIA_MINIMA;
    }

    public static function paraMatricula(Matricula $matricula): float
    {
        $matricula->loadMissing(['turma', 'instituicao']);

        return self::resolver($matricula->turma, $matricula->instituicao);
    }

    private static function instituicaoDaTurma(?Turma $turma): ?Instituicao
    {
        if ($turma === null) {
            return null;
        }

        $turma->loadMissing('instituicao');

        return $turma->instituicao;
    }
}
