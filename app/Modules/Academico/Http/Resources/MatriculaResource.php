<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Pessoas\Http\Resources\AlunoResource;
use App\Modules\Pessoas\Http\Resources\ProfessorResource;
use App\Modules\Financeiro\Http\Resources\ContaResource;
use App\Modules\Financeiro\Http\Resources\ContratoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "MatriculaResource",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "id_aluno", type: "integer", example: 1),
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "data", type: "string", format: "date", example: "2024-03-03"),
        new OA\Property(property: "status", type: "string", example: "ativa"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Matrícula inicial"),
        new OA\Property(property: "aluno", ref: "#/components/schemas/AlunoResource", nullable: true),
        new OA\Property(property: "turma", ref: "#/components/schemas/TurmaResource", nullable: true),
        new OA\Property(property: "contas", type: "array", items: new OA\Items(ref: "#/components/schemas/ContaResource")),
        new OA\Property(property: "id_lead", type: "integer", nullable: true, example: 1)
    ]
)]
class MatriculaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'id_aluno'     => $this->id_aluno,
            'tipo'         => $this->tipo ?? 'turma',
            'id_turma'     => $this->id_turma,
            'id_curso'     => $this->id_curso,
            'id_nivel'     => $this->id_nivel,
            'id_professor' => $this->id_professor,
            'data'         => $this->data ? $this->data->format('Y-m-d') : null,
            'status'       => $this->status,
            'observacoes'  => $this->observacoes,
            'aluno'        => new AlunoResource($this->whenLoaded('aluno')),
            'turma'        => new TurmaResource($this->whenLoaded('turma')),
            'curso'        => new CursoResource($this->whenLoaded('curso')),
            'nivel'        => new NivelResource($this->whenLoaded('nivel')),
            'professor'    => new ProfessorResource($this->whenLoaded('professor')),
            'contas'       => ContaResource::collection($this->whenLoaded('contas')),
            'contrato_id'  => $this->contrato_id,
            'id_lead'      => $this->id_lead,
            'contrato_gerado' => $this->contrato_gerado,
            'contrato'     => new ContratoResource($this->whenLoaded('contrato')),
        ];
    }
}
