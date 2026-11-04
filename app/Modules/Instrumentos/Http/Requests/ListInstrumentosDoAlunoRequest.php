<?php

namespace App\Modules\Instrumentos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListInstrumentosDoAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_aluno' => ['sometimes', 'integer'],
        ];
    }
}
