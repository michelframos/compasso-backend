<?php

namespace App\Modules\Academico\Http\Requests\Reposicao;

use App\Modules\Academico\Models\SolicitacaoReposicao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReposicoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_aluno' => ['sometimes', 'integer'],
            'id_aula_turma_perdida' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::in(SolicitacaoReposicao::STATUS)],
        ];
    }
}
