<?php

namespace App\Modules\Academico\Support;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\TemplateCertificado;

class ResolverTemplateCertificado
{
    public function doCurso(Matricula $matricula): ?TemplateCertificado
    {
        $matricula->loadMissing(['turma.curso.templateCertificado', 'curso.templateCertificado']);

        $curso = $matricula->tipo === 'curso'
            ? $matricula->curso
            : $matricula->turma?->curso;

        return $curso?->templateCertificado;
    }

    public function porId(?int $idTemplate): ?TemplateCertificado
    {
        if ($idTemplate === null) {
            return null;
        }

        return TemplateCertificado::query()->find($idTemplate);
    }
}
