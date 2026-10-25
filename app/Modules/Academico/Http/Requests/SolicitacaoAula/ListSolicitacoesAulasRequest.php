<?php

namespace App\Modules\Academico\Http\Requests\SolicitacaoAula;

use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\Queries\ListSolicitacoesAulasQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSolicitacoesAulasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(ListSolicitacoesAulasQuery::FILTROS_STATUS)],
            'tipo' => ['nullable', Rule::in(SolicitacaoAula::TIPOS)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
