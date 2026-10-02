<?php

namespace App\Modules\Academico\Http\Requests\Aula;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Update Aula Turma Request",
    description: "Payload para atualização de Aula de Turma",
    type: "object",
    properties: [
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "id_professor", type: "integer", example: 1),
        new OA\Property(property: "data", type: "string", format: "date", example: "2023-11-05"),
        new OA\Property(property: "hora_inicio", type: "string", example: "14:00"),
        new OA\Property(property: "hora_termino", type: "string", example: "15:00"),
        new OA\Property(property: "status", type: "string", nullable: true, example: "concluida"),
        new OA\Property(property: "tipo", type: "string", enum: ["regular", "reposicao", "reforco", "extra"], example: "regular"),
        new OA\Property(property: "id_aluno_especifico", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "id_curso", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "id_nivel", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "conteudo_dado", type: "string", nullable: true, example: "Alteração da aula"),
        new OA\Property(property: "notificar", type: "boolean", example: true)
    ]
)]
class UpdateAulaTurmaRequest extends FormRequest
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
            'id_turma' => ['nullable', InstituicaoContext::existsRule('turmas')],
            'id_curso' => ['nullable', InstituicaoContext::existsRule('cursos')],
            'id_nivel' => ['nullable', InstituicaoContext::existsRule('niveis')],
            'id_professor' => ['sometimes', InstituicaoContext::existsRule('professores')],
            'data' => 'sometimes|date',
            'hora_inicio' => 'sometimes|date_format:H:i',
            'hora_termino' => 'sometimes|date_format:H:i|after:hora_inicio',
            'status' => 'nullable|in:agendada,concluida,cancelada',
            'tipo' => 'sometimes|in:regular,reposicao,reforco,extra',
            'id_aluno_especifico' => ['nullable', InstituicaoContext::existsRule('alunos')],
            'conteudo_dado' => 'nullable|string',
            'notificar' => 'nullable|boolean'
        ];
    }
}
