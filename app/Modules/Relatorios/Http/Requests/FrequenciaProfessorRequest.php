<?php

namespace App\Modules\Relatorios\Http\Requests;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;

class FrequenciaProfessorRequest extends FormRequest
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
            'limite_faltas' => ['nullable', 'integer', 'min:1', 'max:20'],
            'id_turma' => ['nullable', 'integer', InstituicaoContext::existsRule('turmas')],
        ];
    }
}
