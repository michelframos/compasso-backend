<?php

namespace App\Modules\Academico\Http\Requests\Turma;

use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;
use App\Enums\TurmaStatus;
use Illuminate\Validation\Rule;

#[OA\Schema(
    schema: "StoreTurmaRequest",
    required: ["id_curso", "id_nivel", "maximo_alunos"],
    properties: [
        new OA\Property(property: "id_curso", type: "integer", example: 1),
        new OA\Property(property: "id_nivel", type: "integer", example: 1),
        new OA\Property(property: "id_professor", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "maximo_alunos", type: "integer", example: 20),
        new OA\Property(property: "descricao", type: "string", maxLength: 255, nullable: true, example: "Turma de Violão Iniciante"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Aulas aos sábados"),
        new OA\Property(property: "status", type: "string", enum: ["planejamento", "aberta", "em_andamento", "pausada", "concluida", "cancelada"], example: "em_andamento"),
        new OA\Property(property: "tipo_agendamento", type: "string", enum: ["datas", "quantidade"], example: "datas"),
        new OA\Property(property: "data_inicio", type: "string", format: "date", nullable: true, example: "2024-01-01"),
        new OA\Property(property: "data_fim", type: "string", format: "date", nullable: true, example: "2024-06-30"),
        new OA\Property(property: "quantidade_aulas", type: "integer", nullable: true, example: 20),
        new OA\Property(property: "valor_mensalidade", type: "number", format: "float", nullable: true, example: 150.00),
        new OA\Property(property: "percentual_comissao_especifico", type: "number", format: "float", nullable: true, example: 20.00),
        new OA\Property(property: "valor_hora_aula_especifico", type: "number", format: "float", nullable: true, example: 60.00),
        new OA\Property(
            property: "horarios",
            type: "array",
            items: new OA\Items(
                properties: [
                    new OA\Property(property: "dia_semana", type: "string", enum: ["segunda", "terca", "quarta", "quinta", "sexta", "sabado", "domingo"]),
                    new OA\Property(property: "hora_inicio", type: "string", example: "14:00"),
                    new OA\Property(property: "hora_termino", type: "string", example: "15:00")
                ]
            )
        )
    ]
)]
class StoreTurmaRequest extends FormRequest
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
            'id_curso' => ['required', 'integer', InstituicaoContext::existsRule('cursos')],
            'id_nivel' => ['required', 'integer', InstituicaoContext::existsRule('niveis')],
            'id_professor' => ['nullable', 'integer', InstituicaoContext::existsRule('professores')],
            'maximo_alunos' => ['required', 'integer', 'min:1'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TurmaStatus::class)],
            'tipo_agendamento' => ['required', Rule::in(['datas', 'quantidade'])],
            'data_inicio' => ['required_if:tipo_agendamento,datas,quantidade', 'nullable', 'date'],
            'data_fim' => ['required_if:tipo_agendamento,datas', 'nullable', 'date', 'after_or_equal:data_inicio'],
            'quantidade_aulas' => ['required_if:tipo_agendamento,quantidade', 'nullable', 'integer', 'min:1'],
            'valor_mensalidade' => ['nullable', 'numeric', 'min:0'],
            'percentual_comissao_especifico' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valor_hora_aula_especifico' => ['nullable', 'numeric', 'min:0'],
            'horarios' => ['nullable', 'array'],
            'horarios.*.dia_semana' => ['required_with:horarios', Rule::in(['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'])],
            'horarios.*.hora_inicio' => ['required_with:horarios', 'date_format:H:i'],
            'horarios.*.hora_termino' => ['required_with:horarios', 'date_format:H:i', 'after:horarios.*.hora_inicio'],
            'contrato_id' => ['nullable', 'integer', InstituicaoContext::existsRule('contratos')],
        ];
    }
}
