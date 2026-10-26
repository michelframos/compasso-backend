<?php

namespace App\Modules\Academico\Http\Requests\JustificativaFalta;

use App\Modules\Academico\Models\JustificativaFalta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListJustificativasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_aluno' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::in(JustificativaFalta::STATUS)],
        ];
    }
}
