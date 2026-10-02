<?php

namespace App\Modules\Academico\Http\Requests\Matricula;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;
use App\Models\Turma;
use App\Models\Matricula;
use App\Enums\TurmaStatus;

#[OA\Schema(
    schema: "StoreMatriculaRequest",
    title: "Criar Matrícula",
    description: "Parâmetros para criar uma matrícula",
    required: ["id_aluno", "tipo", "data"],
    properties: [
        new OA\Property(property: "id_aluno", type: "integer", example: 1),
        new OA\Property(property: "tipo", type: "string", example: "turma"),
        new OA\Property(property: "id_turma", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "id_curso", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "id_nivel", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "id_professor", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "data", type: "string", format: "date", example: "2023-01-15"),
        new OA\Property(property: "status", type: "string", example: "ativa", nullable: true),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Aluno transferido"),
        new OA\Property(property: "id_lead", type: "integer", nullable: true, example: 1)
    ]
)]
class StoreMatriculaRequest extends FormRequest
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
            'id_aluno'     => ['required', InstituicaoContext::existsRule('alunos')],
            'tipo'         => 'required|in:turma,curso',
            'id_turma'     => ['nullable', InstituicaoContext::existsRule('turmas')],
            'id_curso'     => ['nullable', InstituicaoContext::existsRule('cursos')],
            'id_nivel'     => ['nullable', InstituicaoContext::existsRule('niveis')],
            'id_professor' => ['nullable', InstituicaoContext::existsRule('professores')],
            'data'         => 'required|date',
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
            $idCurso = $this->input('id_curso');
            $idNivel = $this->input('id_nivel');
            $idProfessor = $this->input('id_professor');
            $idAluno = $this->input('id_aluno');

            if ($tipo === 'turma') {
                // Para matrícula em turma, id_turma é obrigatório
                if (!$idTurma) {
                    $validator->errors()->add('id_turma', 'A turma é obrigatória para matrículas do tipo turma.');
                    return;
                }

                $turma = Turma::withCount('matriculas')->find($idTurma);

                if ($turma) {
                    if ($turma->status === TurmaStatus::CONCLUIDA || $turma->status === TurmaStatus::CANCELADA) {
                        $validator->errors()->add('id_turma', 'Não é possível realizar matrícula em uma turma concluída ou cancelada.');
                    }

                    if ($turma->matriculas_count >= $turma->maximo_alunos) {
                        $validator->errors()->add('id_turma', 'A turma selecionada já atingiu sua capacidade máxima de alunos.');
                    }
                }

                if ($idAluno && $idTurma) {
                    $matriculaExistente = Matricula::where('id_aluno', $idAluno)
                        ->where('id_turma', $idTurma)
                        ->whereNotIn('status', ['cancelada', 'transferida'])
                        ->exists();

                    if ($matriculaExistente) {
                        $validator->errors()->add('id_aluno', 'Este aluno já possui uma matrícula ativa nesta turma.');
                    }
                }
            }

            if ($tipo === 'curso') {
                // Para matrícula em curso, id_curso, id_nivel e id_professor são obrigatórios
                if (!$idCurso) {
                    $validator->errors()->add('id_curso', 'O curso é obrigatório para matrículas do tipo curso.');
                }
                if (!$idNivel) {
                    $validator->errors()->add('id_nivel', 'O nível é obrigatório para matrículas do tipo curso.');
                }
                if (!$idProfessor) {
                    $validator->errors()->add('id_professor', 'O professor é obrigatório para matrículas do tipo curso.');
                }
            }
        });
    }
}
