<?php

namespace App\Modules\Academico\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MaterialTurmaArquivoService
{
    private const DISCO = 'public';

    /** @return array{file_path: string, file_type: string} */
    public function armazenar(UploadedFile $arquivo, int $idTurma): array
    {
        return [
            'file_path' => $arquivo->store('materiais_turmas/' . $idTurma, self::DISCO),
            'file_type' => mb_substr((string) $arquivo->getClientMimeType(), 0, 255),
        ];
    }

    public function remover(?string $caminho): void
    {
        if ($caminho && Storage::disk(self::DISCO)->exists($caminho)) {
            Storage::disk(self::DISCO)->delete($caminho);
        }
    }
}
