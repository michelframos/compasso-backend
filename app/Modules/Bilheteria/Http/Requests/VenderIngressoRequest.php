<?php

namespace App\Modules\Bilheteria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VenderIngressoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comprador_nome' => ['nullable', 'string', 'max:255'],
            'comprador_documento' => ['nullable', 'string', 'max:30'],
            'cupom' => ['nullable', 'string', 'max:50'],
            'data_vencimento' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
