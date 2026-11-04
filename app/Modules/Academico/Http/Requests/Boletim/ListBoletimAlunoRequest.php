<?php

namespace App\Modules\Academico\Http\Requests\Boletim;

use Illuminate\Foundation\Http\FormRequest;

class ListBoletimAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_matricula' => ['sometimes', 'integer', 'min:1'],
            'id_aluno' => ['sometimes', 'integer'],
        ];
    }
}
