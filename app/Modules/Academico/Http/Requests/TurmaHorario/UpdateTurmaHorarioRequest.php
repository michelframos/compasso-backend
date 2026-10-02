<?php

namespace App\Modules\Academico\Http\Requests\TurmaHorario;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateTurmaHorarioRequest",
    title: "UpdateTurmaHorarioRequest",
    properties: [
        new OA\Property(property: "dia_semana", type: "string", example: "quarta"),
        new OA\Property(property: "hora_inicio", format: "time", type: "string", example: "15:00"),
        new OA\Property(property: "hora_termino", format: "time", type: "string", example: "17:00"),
    ]
)]
class UpdateTurmaHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dia_semana' => 'sometimes|in:segunda,terca,quarta,quinta,sexta,sabado,domingo',
            'hora_inicio' => 'sometimes|date_format:H:i',
            'hora_termino' => 'sometimes|date_format:H:i|after:hora_inicio',
        ];
    }
}
