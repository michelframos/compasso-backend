<?php

namespace App\Modules\Bilheteria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateIngressoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'largura' => ['required', 'numeric', 'min:1'],
            'altura' => ['required', 'numeric', 'min:1'],
            'unidade' => ['required', 'in:px,cm'],
            'conteudo' => ['nullable', 'string'],
        ];
    }
}
