<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Pessoas\Http\Resources\ProfessorResource;
use App\Modules\Financeiro\Http\Resources\ContratoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "TurmaResource",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "id_curso", type: "integer", example: 1),
        new OA\Property(property: "id_nivel", type: "integer", example: 1),
        new OA\Property(property: "id_professor", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "maximo_alunos", type: "integer", example: 20),
        new OA\Property(property: "descricao", type: "string", nullable: true, example: "Turma de ViolÃ£o Iniciante"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Aulas aos sÃ¡bados"),
        new OA\Property(property: "status", type: "string", example: "em_andamento"),
        new OA\Property(property: "tipo_agendamento", type: "string", example: "datas"),
        new OA\Property(property: "data_inicio", type: "string", format: "date", nullable: true, example: "2024-01-01"),
        new OA\Property(property: "data_fim", type: "string", format: "date", nullable: true, example: "2024-06-30"),
        new OA\Property(property: "quantidade_aulas", type: "integer", nullable: true, example: 20),
        new OA\Property(property: "valor_mensalidade", type: "number", format: "float", nullable: true, example: 150.00),
        new OA\Property(property: "percentual_comissao_especifico", type: "number", format: "float", nullable: true, example: 20.00),
        new OA\Property(property: "valor_hora_aula_especifico", type: "number", format: "float", nullable: true, example: 60.00),
        new OA\Property(property: "curso", ref: "#/components/schemas/CursoResource", nullable: true),
        new OA\Property(property: "nivel", ref: "#/components/schemas/NivelResource", nullable: true),
        new OA\Property(property: "professor", ref: "#/components/schemas/ProfessorResource", nullable: true),
        new OA\Property(property: "horarios", type: "array", items: new OA\Items(ref: "#/components/schemas/TurmaHorarioResource")),
        new OA\Property(property: "matriculas", type: "array", items: new OA\Items(ref: "#/components/schemas/MatriculaResource")),
        new OA\Property(property: "matriculas_count", type: "integer", example: 5)
    ]
)]
class TurmaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_curso' => $this->id_curso,
            'id_nivel' => $this->id_nivel,
            'id_professor' => $this->id_professor,
            'maximo_alunos' => $this->maximo_alunos,
            'descricao' => $this->descricao,
            'observacoes' => $this->observacoes,
            'status' => $this->status,
            'tipo_agendamento' => $this->tipo_agendamento,
            'data_inicio' => $this->data_inicio,
            'data_fim' => $this->data_fim,
            'quantidade_aulas' => $this->quantidade_aulas,
            'valor_mensalidade' => $this->valor_mensalidade,
            'percentual_comissao_especifico' => $this->percentual_comissao_especifico,
            'valor_hora_aula_especifico' => $this->valor_hora_aula_especifico,
            'curso' => new CursoResource($this->whenLoaded('curso')),
            'nivel' => new NivelResource($this->whenLoaded('nivel')),
            'professor' => new ProfessorResource($this->whenLoaded('professor')),
            'horarios' => TurmaHorarioResource::collection($this->whenLoaded('horarios')),
            'matriculas' => MatriculaResource::collection($this->whenLoaded('matriculas')),
            'matriculas_count' => $this->whenCounted('matriculas'),
            'contrato_id' => $this->contrato_id,
            'contrato' => new ContratoResource($this->whenLoaded('contrato')),
        ];
    }
}
