<?php

namespace App\Modules\Academico\UseCases\MaterialTurma;

use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Services\MaterialTurmaArquivoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AtualizarMaterialTurmaUseCase
{
    public function __construct(private readonly MaterialTurmaArquivoService $arquivos) {}

    /**
     * Um novo arquivo substitui o link e um novo link substitui o arquivo; o material nunca fica sem nenhum dos dois.
     *
     * @param  array{titulo?: string, descricao?: ?string, link?: ?string, publico?: bool}  $dados
     */
    public function execute(MaterialTurma $material, array $dados, ?UploadedFile $arquivo = null): MaterialTurma
    {
        unset($dados['file']);

        if ($arquivo) {
            $this->arquivos->remover($material->file_path);
            $dados = [...$dados, ...$this->arquivos->armazenar($arquivo, $material->id_turma), 'link' => null];
        } elseif (! empty($dados['link'])) {
            $this->arquivos->remover($material->file_path);
            $dados = [...$dados, 'file_path' => null, 'file_type' => null];
        } elseif (array_key_exists('link', $dados) && ! $material->file_path) {
            throw ValidationException::withMessages(['link' => 'Informe um link ou envie um arquivo.']);
        }

        $material->update($dados);

        return $material->refresh();
    }
}
