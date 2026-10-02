<?php

namespace App\Modules\Core\Http\Requests\Instituicao;

use App\Modules\Core\Support\LimiteAvisosProfessor;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateComunicacaoProfessorRequest',
    required: ['limite_avisos_dia'],
    properties: [
        new OA\Property(property: 'limite_avisos_dia', type: 'integer', minimum: LimiteAvisosProfessor::MINIMO, maximum: LimiteAvisosProfessor::MAXIMO, example: 10, description: 'Avisos manuais que cada professor pode enviar por dia (os automáticos de alteração de aula não contam)'),
    ]
)]
class UpdateComunicacaoProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limite_avisos_dia' => ['required', 'integer', 'min:'.LimiteAvisosProfessor::MINIMO, 'max:'.LimiteAvisosProfessor::MAXIMO],
        ];
    }

    public function messages(): array
    {
        return [
            'limite_avisos_dia.min' => 'O limite deve ser de pelo menos :min aviso por dia.',
            'limite_avisos_dia.max' => 'O limite pode ser de no máximo :max avisos por dia.',
        ];
    }
}
