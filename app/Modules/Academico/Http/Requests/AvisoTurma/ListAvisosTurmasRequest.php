<?php

namespace App\Modules\Academico\Http\Requests\AvisoTurma;

use App\Modules\Academico\Models\AvisoTurma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAvisosTurmasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_turma' => ['nullable', 'integer'],
            'origem' => ['nullable', Rule::in(AvisoTurma::ORIGENS)],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
