<?php

namespace App\Modules\Academico\Http\Requests\Curso;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreCursoRequest",
    required: ["nome"],
    properties: [
        new OA\Property(property: "nome", type: "string", maxLength: 100, example: "Violoncelo"),
        new OA\Property(property: "descricao", type: "string", nullable: true, example: "Curso de violoncelo avançado")
    ]
)]
class StoreCursoRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:100'],
            'descricao' => ['nullable', 'string'],
            'contrato_id' => ['nullable', 'integer', InstituicaoContext::existsRule('contratos')],
        ];
    }
}
