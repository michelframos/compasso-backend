<?php

namespace App\Modules\Espetaculos\Http\Requests\Ensaio;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;

class ListEnsaiosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_apresentacao' => ['nullable', 'integer', InstituicaoContext::existsRule('apresentacoes')],
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
        ];
    }
}
