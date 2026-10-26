<?php

namespace App\Modules\Academico\Http\Requests\MaterialTurma;

use Illuminate\Foundation\Http\FormRequest;

class ListMateriaisDoAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_turma' => ['sometimes', 'integer', 'min:1'],
            'id_aluno' => ['sometimes', 'integer'],
        ];
    }
}
