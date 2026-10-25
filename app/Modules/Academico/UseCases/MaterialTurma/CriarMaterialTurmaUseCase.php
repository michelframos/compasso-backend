<?php

namespace App\Modules\Academico\UseCases\MaterialTurma;

use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Services\MaterialTurmaArquivoService;
use Illuminate\Http\UploadedFile;

class CriarMaterialTurmaUseCase
{
    public function __construct(private readonly MaterialTurmaArquivoService $arquivos) {}

    /**
     * Quando arquivo e link são enviados juntos, o arquivo prevalece.
     *
     * @param  array{id_turma: int, titulo: string, descricao?: ?string, link?: ?string, publico?: bool}  $dados
     */
    public function execute(array $dados, ?UploadedFile $arquivo = null): MaterialTurma
    {
        unset($dados['file']);

        if ($arquivo) {
            $dados = [...$dados, ...$this->arquivos->armazenar($arquivo, (int) $dados['id_turma']), 'link' => null];
        }

        return MaterialTurma::create($dados);
    }
}
