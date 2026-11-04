<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Certificado;
use App\Modules\Academico\Models\TemplateCertificado;
use App\Modules\Academico\Support\CertificadoFontes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

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

        if (filled($certificado->conteudo_gerado)) {
            return $this->downloadComTemplate($certificado);
        }

        return $this->downloadLegado($certificado);
    }

    private function downloadComTemplate(Certificado $certificado): Response
    {
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');
        CertificadoFontes::registrarNoDompdf($pdf->getDomPDF());

        $pdf->loadView('pdf.certificado-template', [
            'conteudo' => $certificado->conteudo_gerado,
            'fundo_data_uri' => $this->fundoDataUri($certificado->fundo_path),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('certificado_'.$certificado->codigo_verificacao.'.pdf');
    }

    private function downloadLegado(Certificado $certificado): Response
    {
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

    private function fundoDataUri(?string $caminho): ?string
    {
        if (! $caminho) {
            return null;
        }

        $disco = Storage::disk(TemplateCertificado::DISCO_FUNDO);
        if (! $disco->exists($caminho)) {
            return null;
        }

        $conteudo = $disco->get($caminho);
        $mime = $disco->mimeType($caminho) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($conteudo);
    }
}
