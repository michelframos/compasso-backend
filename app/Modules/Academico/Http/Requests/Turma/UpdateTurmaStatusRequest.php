<?php

namespace App\Modules\Academico\Http\Requests\Turma;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;
use App\Enums\TurmaStatus;
use Illuminate\Validation\Rule;

#[OA\Schema(
    schema: "UpdateTurmaStatusRequest",
    required: ["status"],
    properties: [
        new OA\Property(
            property: "status",
            type: "string",
            enum: ["planejamento", "aberta", "em_andamento", "pausada", "concluida", "cancelada"],
            example: "em_andamento"
        )
    ]
)]
class UpdateTurmaStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TurmaStatus::class)],
        ];
    }
}
