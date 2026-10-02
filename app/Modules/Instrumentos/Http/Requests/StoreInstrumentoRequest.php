<?php

namespace App\Modules\Instrumentos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstrumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'tipo' => 'required|string|max:255',
            'numero_serie' => 'nullable|string|max:255',
            'status' => 'nullable|in:disponivel,emprestado,manutencao,inativo',
            'observacoes' => 'nullable|string',
        ];
    }
}
