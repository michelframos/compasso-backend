<?php

namespace App\Modules\Financeiro\Http\Requests\AlunoContrato;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarContratoDoAlunoRequest extends FormRequest
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
