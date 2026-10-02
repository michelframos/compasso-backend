<?php

namespace App\Modules\Relatorios\Http\Requests;

use App\Modules\Relatorios\Services\Remuneracao\Competencia;
use Illuminate\Foundation\Http\FormRequest;

class FecharMesProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mes' => ['required', 'date_format:Y-m'],
            'data_vencimento' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function competencia(): Competencia
    {
        return Competencia::de($this->validated('mes'));
    }
}
