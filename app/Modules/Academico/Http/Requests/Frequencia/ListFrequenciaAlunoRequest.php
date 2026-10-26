<?php

namespace App\Modules\Academico\Http\Requests\Frequencia;

use Illuminate\Foundation\Http\FormRequest;

class ListFrequenciaAlunoRequest extends FormRequest
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
