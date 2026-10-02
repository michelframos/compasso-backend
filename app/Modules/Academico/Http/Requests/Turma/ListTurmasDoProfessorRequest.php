<?php

namespace App\Modules\Academico\Http\Requests\Turma;

use App\Modules\Academico\Queries\ListTurmasDoProfessorQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTurmasDoProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'situacao' => ['nullable', Rule::in(ListTurmasDoProfessorQuery::SITUACOES)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
