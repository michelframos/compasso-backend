<?php

namespace App\Modules\Financeiro\Http\Requests\Conta;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;

class GerarCarneAlunoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conta_ids' => ['required', 'array', 'min:1'],
            'conta_ids.*' => ['integer', InstituicaoContext::existsRule('contas')],
            'titulo' => ['nullable', 'string', 'max:100'],
            'id_aluno' => ['sometimes', 'integer'],
        ];
    }
}
