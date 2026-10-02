<?php

namespace App\Modules\Academico\Http\Requests\TurmaHorario;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreTurmaHorarioRequest",
    title: "StoreTurmaHorarioRequest",
    required: ["id_turma", "dia_semana", "hora_inicio", "hora_termino"],
    properties: [
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "dia_semana", type: "string", example: "terca"),
        new OA\Property(property: "hora_inicio", format: "time", type: "string", example: "14:00"),
        new OA\Property(property: "hora_termino", format: "time", type: "string", example: "16:00"),
    ]
)]
class StoreTurmaHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_turma' => ['required', InstituicaoContext::existsRule('turmas')],
            'dia_semana' => 'required|in:segunda,terca,quarta,quinta,sexta,sabado,domingo',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_termino' => 'required|date_format:H:i|after:hora_inicio',
        ];
    }
}
