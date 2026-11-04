<?php

namespace App\Modules\Financeiro\Http\Requests\Conta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContasDoAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'situacao' => ['sometimes', Rule::in(['em_aberto', 'pagas', 'atrasadas'])],
            'id_matricula' => ['sometimes', 'integer', 'min:1'],
            'id_aluno' => ['sometimes', 'integer'],
        ];
    }
}
