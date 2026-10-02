<?php

namespace App\Modules\Pessoas\Http\Requests\MedidaAluno;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Store Medida Aluno Request",
    description: "Request body data for storing a new measurement",
    type: "object",
    required: ["id_aluno", "medida_torax", "medida_cintura", "medida_quadril", "medida_altura"],
    properties: [
        new OA\Property(property: "id_aluno", type: "integer", example: 1, description: "ID of the student"),
        new OA\Property(property: "medida_torax", type: "number", format: "float", example: 90.5, description: "Chest measurement"),
        new OA\Property(property: "medida_cintura", type: "number", format: "float", example: 75.0, description: "Waist measurement"),
        new OA\Property(property: "medida_quadril", type: "number", format: "float", example: 100.0, description: "Hip measurement"),
        new OA\Property(property: "medida_altura", type: "number", format: "float", example: 170.0, description: "Height measurement"),
        new OA\Property(property: "ativo", type: "boolean", example: true, description: "Status of the measurement")
    ]
)]
class StoreMedidaAlunoRequest extends FormRequest
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
            'id_aluno' => ['required', InstituicaoContext::existsRule('alunos')],
            'medida_torax' => 'required|numeric',
            'medida_cintura' => 'required|numeric',
            'medida_quadril' => 'required|numeric',
            'medida_altura' => 'required|numeric',
            'ativo' => 'boolean',
        ];
    }
}
