<?php

namespace App\Modules\Academico\Http\Requests\Aluno;

use App\Modules\Academico\Queries\ListAlunosDoProfessorQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAlunosDoProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['nullable', Rule::in(ListAlunosDoProfessorQuery::TIPOS)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
