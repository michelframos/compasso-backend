<?php

namespace App\Modules\Espetaculos\Http\Requests\Apresentacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListApresentacoesProfessorRequest extends FormRequest
{
    public const SITUACOES = ['proximas', 'passadas', 'todas'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'situacao' => ['nullable', Rule::in(self::SITUACOES)],
        ];
    }
}
