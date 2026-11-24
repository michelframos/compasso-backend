<?php

namespace App\Modules\Bilheteria\Services;

use App\Modules\Bilheteria\Models\TemplateIngresso;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateIngressoFundoService
{
    public function salvarFundo(UploadedFile $arquivo): string
    {
        $instituicaoId = InstituicaoContext::id() ?? 0;
        $nome = Str::uuid()->toString().'.'.$arquivo->getClientOriginalExtension();

        return $arquivo->storeAs("ingressos/fundos/{$instituicaoId}", $nome, TemplateIngresso::DISCO_FUNDO);
    }

    public function salvarElemento(UploadedFile $arquivo): string
    {
        $instituicaoId = InstituicaoContext::id() ?? 0;
        $nome = Str::uuid()->toString().'.'.$arquivo->getClientOriginalExtension();

        return $arquivo->storeAs("ingressos/elementos/{$instituicaoId}", $nome, TemplateIngresso::DISCO_FUNDO);
    }

    public function remover(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk(TemplateIngresso::DISCO_FUNDO)->delete($path);
    }

    public function copiarFundo(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disco = Storage::disk(TemplateIngresso::DISCO_FUNDO);
        if (! $disco->exists($path)) {
            return null;
        }

        $instituicaoId = InstituicaoContext::id() ?? 0;
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'png';
        $destino = "ingressos/fundos/{$instituicaoId}/".Str::uuid()->toString().'.'.$ext;
        $disco->copy($path, $destino);

        return $destino;
    }
}
