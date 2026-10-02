<?php

namespace App\Modules\Academico\Http\Requests\Disponibilidade;

use Illuminate\Foundation\Http\FormRequest;

class ProfessoresLivresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => 'required|date_format:Y-m-d',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_termino' => 'required|date_format:H:i|after:hora_inicio',
            'ignorar_aula' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'hora_termino.after' => 'O término deve ser depois do início.',
        ];
    }
}
