<?php

namespace App\Modules\Bilheteria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidarUtilizarIngressoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero' => ['nullable', 'string', 'max:40', 'required_without:codigo_qr'],
            'codigo_qr' => ['nullable', 'string', 'max:64', 'required_without:numero'],
        ];
    }
}
