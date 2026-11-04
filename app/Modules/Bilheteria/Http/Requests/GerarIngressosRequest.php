<?php

namespace App\Modules\Bilheteria\Http\Requests;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;

class GerarIngressosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantidade' => ['required', 'integer', 'min:1', 'max:500'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'id_template_ingresso' => ['nullable', InstituicaoContext::existsRule('templates_ingressos')],
        ];
    }
}
