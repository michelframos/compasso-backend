<?php

namespace App\Modules\Pessoas\Http\Requests\MedidaAluno;

use Illuminate\Foundation\Http\FormRequest;

class ListMedidasDoAlunoRequest extends FormRequest
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
