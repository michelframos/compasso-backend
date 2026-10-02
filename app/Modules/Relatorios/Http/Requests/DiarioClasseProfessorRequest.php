<?php

namespace App\Modules\Relatorios\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiarioClasseProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
        ];
    }
}
