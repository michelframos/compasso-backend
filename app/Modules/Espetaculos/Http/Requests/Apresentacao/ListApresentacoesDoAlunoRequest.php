<?php

namespace App\Modules\Espetaculos\Http\Requests\Apresentacao;

use Illuminate\Foundation\Http\FormRequest;

class ListApresentacoesDoAlunoRequest extends FormRequest
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
