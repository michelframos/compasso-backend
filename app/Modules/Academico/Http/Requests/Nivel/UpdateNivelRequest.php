<?php

namespace App\Modules\Academico\Http\Requests\Nivel;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateNivelRequest",
    properties: [
        new OA\Property(property: "nome", type: "string", maxLength: 100, example: "Avançado"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Para alunos com domínio do instrumento"),
        new OA\Property(property: "curso_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "ordem", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "idade_minima", type: "integer", nullable: true, example: 3),
        new OA\Property(property: "idade_maxima", type: "integer", nullable: true, example: 6),
        new OA\Property(property: "cor_identificacao", type: "string", nullable: true, example: "#FF5733"),
        new OA\Property(property: "expectativas_aprendizado", type: "string", nullable: true, example: "O aluno deve conseguir...")
    ]
)]
class UpdateNivelRequest extends FormRequest
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
            'observacoes' => ['nullable', 'string'],
            'curso_id' => ['nullable', 'integer', InstituicaoContext::existsRule('cursos')],
            'ordem' => ['nullable', 'integer'],
            'idade_minima' => ['nullable', 'integer', 'min:0'],
            'idade_maxima' => ['nullable', 'integer', 'gte:idade_minima'],
            'cor_identificacao' => ['nullable', 'string', 'max:10'],
            'expectativas_aprendizado' => ['nullable', 'string'],
        ];
    }
}
