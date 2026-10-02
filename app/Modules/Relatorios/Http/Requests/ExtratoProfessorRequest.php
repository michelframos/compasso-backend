<?php

namespace App\Modules\Relatorios\Http\Requests;

use App\Modules\Relatorios\Services\Remuneracao\Competencia;
use Illuminate\Foundation\Http\FormRequest;

class ExtratoProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mes' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
        ];
    }

    public function messages(): array
    {
        return [
            'mes.before_or_equal' => 'Não há extrato para meses futuros.',
        ];
    }

    public function competencia(): Competencia
    {
        $mes = $this->validated('mes');

        return $mes ? Competencia::de($mes) : Competencia::atual();
    }
}
