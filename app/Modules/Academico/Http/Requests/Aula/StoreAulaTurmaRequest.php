<?php

namespace App\Modules\Academico\Http\Requests\Aula;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Store Aula Turma Request",
    description: "Payload para criação de Aula de Turma",
    type: "object",
    required: ["id_turma", "id_professor", "data", "hora_inicio", "hora_termino"],
    properties: [
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "id_professor", type: "integer", example: 1),
        new OA\Property(property: "data", type: "string", format: "date", example: "2023-11-05"),
        new OA\Property(property: "hora_inicio", type: "string", example: "14:00"),
        new OA\Property(property: "hora_termino", type: "string", example: "15:00"),
        new OA\Property(property: "status", type: "string", nullable: true, example: "agendada"),
        new OA\Property(property: "tipo", type: "string", enum: ["regular", "reposicao", "reforco", "extra"], example: "regular"),
        new OA\Property(property: "id_aluno_especifico", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "id_curso", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "id_nivel", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "conteudo_dado", type: "string", nullable: true, example: "Teste inicial"),
        new OA\Property(property: "notificar", type: "boolean", example: true),
        new OA\Property(property: "recorrente", type: "boolean", nullable: true, example: false),
        new OA\Property(property: "quantidade_ocorrencias", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "frequencia_ocorrencias", type: "string", enum: ["diaria", "semanal", "quinzenal", "mensal"], nullable: true, example: "semanal")
    ]
)]
class StoreAulaTurmaRequest extends FormRequest
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
            'id_curso' => ['required_without:id_turma', 'nullable', InstituicaoContext::existsRule('cursos')],
            'id_nivel' => ['nullable', InstituicaoContext::existsRule('niveis')],
            'id_professor' => ['required', InstituicaoContext::existsRule('professores')],
            'data' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_termino' => 'required|date_format:H:i|after:hora_inicio',
            'status' => 'nullable|in:agendada,concluida,cancelada',
            'tipo' => 'sometimes|in:regular,reposicao,reforco,extra',
            'id_aluno_especifico' => ['required_without:id_turma', 'nullable', InstituicaoContext::existsRule('alunos')],
            'conteudo_dado' => 'nullable|string',
            'gerar_conta' => 'nullable|boolean',
            'valor_conta' => 'required_if:gerar_conta,true|nullable|numeric|min:0',
            'data_vencimento_conta' => 'required_if:gerar_conta,true|nullable|date',
            'id_categoria_conta' => ['required_if:gerar_conta,true', 'nullable', InstituicaoContext::existsRule('categorias_contas')],
            'notificar' => 'nullable|boolean',
            'recorrente' => 'nullable|boolean',
            'quantidade_ocorrencias' => 'nullable|integer|min:1|max:50',
            'frequencia_ocorrencias' => 'nullable|in:diaria,semanal,quinzenal,mensal',
        ];
    }
}
