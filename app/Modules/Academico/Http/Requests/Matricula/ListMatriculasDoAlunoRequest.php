<?php

namespace App\Modules\Academico\Http\Requests\Matricula;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMatriculasDoAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['encerradas'])],
            'id_aluno' => ['sometimes', 'integer'],
        ];
    }
}
