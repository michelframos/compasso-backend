<?php

namespace App\Modules\Academico\UseCases\MaterialTurma;

use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Services\MaterialTurmaArquivoService;

class ExcluirMaterialTurmaUseCase
{
    public function __construct(private readonly MaterialTurmaArquivoService $arquivos) {}

    public function execute(MaterialTurma $material): void
    {
        $this->arquivos->remover($material->file_path);
        $material->delete();
    }
}
