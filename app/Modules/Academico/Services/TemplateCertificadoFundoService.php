<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\TemplateCertificado;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TemplateCertificadoFundoService
{
    public function armazenar(TemplateCertificado $template, UploadedFile $arquivo): string
    {
        $this->remover($template->fundo_path);

        $pasta = 'certificados/fundos/'.(InstituicaoContext::id() ?? 'geral');

        return $arquivo->store($pasta, TemplateCertificado::DISCO_FUNDO);
    }

    public function armazenarElemento(UploadedFile $arquivo): string
    {
        $pasta = 'certificados/elementos/'.(InstituicaoContext::id() ?? 'geral');

        return $arquivo->store($pasta, TemplateCertificado::DISCO_FUNDO);
    }

    public function copiarParaSnapshot(?string $caminhoOrigem, int $idCertificado): ?string
    {
        if (! $caminhoOrigem) {
            return null;
        }

        $disco = Storage::disk(TemplateCertificado::DISCO_FUNDO);
        if (! $disco->exists($caminhoOrigem)) {
            return null;
        }

        $extensao = pathinfo($caminhoOrigem, PATHINFO_EXTENSION) ?: 'jpg';
        $destino = 'certificados/snapshots/'.$idCertificado.'.'.$extensao;
        $disco->put($destino, $disco->get($caminhoOrigem));

        return $destino;
    }

    public function copiarFundo(?string $caminhoOrigem): ?string
    {
        if (! $caminhoOrigem) {
            return null;
        }

        $disco = Storage::disk(TemplateCertificado::DISCO_FUNDO);
        if (! $disco->exists($caminhoOrigem)) {
            return null;
        }

        $extensao = pathinfo($caminhoOrigem, PATHINFO_EXTENSION) ?: 'jpg';
        $pasta = 'certificados/fundos/'.(InstituicaoContext::id() ?? 'geral');
        $destino = $pasta.'/'.uniqid('fundo_', true).'.'.$extensao;
        $disco->put($destino, $disco->get($caminhoOrigem));

        return $destino;
    }

    public function remover(?string $caminho): void
    {
        if (! $caminho) {
            return;
        }

        $disco = Storage::disk(TemplateCertificado::DISCO_FUNDO);
        if ($disco->exists($caminho)) {
            $disco->delete($caminho);
        }
    }
}
