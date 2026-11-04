<?php

namespace App\Modules\Bilheteria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateIngressoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:150'],
            'largura' => ['sometimes', 'required', 'numeric', 'min:1'],
            'altura' => ['sometimes', 'required', 'numeric', 'min:1'],
            'unidade' => ['sometimes', 'required', 'in:px,cm'],
            'conteudo' => ['nullable', 'string'],
        ];
    }
}
