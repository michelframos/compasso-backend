<?php

namespace App\Modules\Academico\Http\Requests\JustificativaFalta;

use App\Modules\Academico\Models\JustificativaFalta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RegistrarJustificativaRequest',
    required: ['id_aula_turma', 'tipo', 'motivo'],
    properties: [
        new OA\Property(property: 'id_aula_turma', type: 'integer'),
        new OA\Property(property: 'tipo', type: 'string', enum: JustificativaFalta::TIPOS),
        new OA\Property(property: 'motivo', type: 'string', maxLength: 2000),
        new OA\Property(property: 'anexo', type: 'string', format: 'binary', nullable: true),
        new OA\Property(property: 'id_aluno', type: 'integer', nullable: true, description: 'Obrigatório para o responsável'),
    ]
)]
class RegistrarJustificativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_aula_turma' => ['required', 'integer'],
            'tipo' => ['required', Rule::in(JustificativaFalta::TIPOS)],
            'motivo' => ['required', 'string', 'max:2000'],
            'anexo' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'id_aluno' => ['sometimes', 'integer'],
        ];
    }
}
