<?php

namespace App\Modules\Academico\Http\Requests\SugestaoProgressao;

use App\Modules\Academico\Queries\ListSugestoesProgressaoQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSugestoesProgressaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(ListSugestoesProgressaoQuery::FILTROS_STATUS)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
