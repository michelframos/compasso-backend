<?php

namespace App\Modules\Academico\Http\Requests\Matricula;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;
use App\Models\Turma;
use App\Enums\TurmaStatus;

#[OA\Schema(
    schema: "UpdateMatriculaRequest",
    title: "Atualizar Matrícula",
    description: "Parâmetros para atualizar uma matrícula",
    properties: [
        new OA\Property(property: "id_aluno", type: "integer", example: 1),
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "data", type: "string", format: "date", example: "2023-01-15"),
        new OA\Property(property: "status", type: "string", example: "inativa", nullable: true),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Observações da matrícula alteradas"),
        new OA\Property(property: "id_lead", type: "integer", nullable: true, example: 1)
    ]
)]
class UpdateMatriculaRequest extends FormRequest
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
            'id_aluno'     => ['sometimes', InstituicaoContext::existsRule('alunos')],
            'tipo'         => 'sometimes|in:turma,curso',
            'id_turma'     => ['nullable', InstituicaoContext::existsRule('turmas')],
            'id_curso'     => ['nullable', InstituicaoContext::existsRule('cursos')],
            'id_nivel'     => ['nullable', InstituicaoContext::existsRule('niveis')],
            'id_professor' => ['nullable', InstituicaoContext::existsRule('professores')],
            'data'         => 'sometimes|date',
            'status'       => 'nullable|string|max:255',
            'observacoes'  => 'nullable|string',
            'contrato_id'  => ['nullable', 'integer', InstituicaoContext::existsRule('contratos')],
            'id_lead'      => ['nullable', 'integer', InstituicaoContext::existsRule('leads')],
            'contrato_gerado' => 'nullable|string',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $tipo    = $this->input('tipo');
            $idTurma = $this->input('id_turma');
            $matricula = $this->route('matricula');

            // Só valida capac. de turma se o tipo for turma e a turma estiver mudando
            if ($tipo === 'turma' && $idTurma && ($matricula === null || (int) $matricula->id_turma !== (int) $idTurma)) {
                $turma = Turma::withCount('matriculas')->find($idTurma);

                if ($turma) {
                    if ($turma->status === TurmaStatus::CONCLUIDA || $turma->status === TurmaStatus::CANCELADA) {
                        $validator->errors()->add('id_turma', 'Não é possível transferir a matrícula para uma turma concluída ou cancelada.');
                    }

                    if ($turma->matriculas_count >= $turma->maximo_alunos) {
                        $validator->errors()->add('id_turma', 'A turma selecionada já atingiu sua capacidade máxima de alunos.');
                    }
                }
            }
        });
    }
}
