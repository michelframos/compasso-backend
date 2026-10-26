<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Certificado;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class CertificadoPdfService
{
    public function download(Certificado $certificado): Response
    {
        $certificado->loadMissing([
            'instituicao',
            'matricula.aluno.usuario',
            'matricula.turma.curso',
            'matricula.turma.nivel',
            'matricula.curso',
            'matricula.nivel',
        ]);

        $matricula = $certificado->matricula;
        $curso = $matricula?->tipo === 'curso'
            ? $matricula->curso?->nome
            : $matricula?->turma?->curso?->nome;
        $turma = $matricula?->tipo === 'turma'
            ? ($matricula->turma?->apelido() ?? $matricula->turma?->descricao)
            : null;
        $nivel = $matricula?->tipo === 'curso'
            ? $matricula->nivel?->nome
            : $matricula?->turma?->nivel?->nome;

        $pdf = Pdf::loadView('pdf.certificado', [
            'escola' => $certificado->instituicao?->nome_fantasia ?? 'Escola',
            'aluno' => $matricula?->aluno?->usuario?->nome ?? 'Aluno',
            'curso' => $curso,
            'turma' => $turma,
            'nivel' => $nivel,
            'carga_horaria' => number_format((float) $certificado->carga_horaria, 2, ',', '.'),
            'emitido_em' => $certificado->emitido_em?->format('d/m/Y'),
            'codigo' => $certificado->codigo_verificacao,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('certificado_'.$certificado->codigo_verificacao.'.pdf');
    }
}
