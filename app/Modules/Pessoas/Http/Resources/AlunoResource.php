<?php

namespace App\Modules\Pessoas\Http\Resources;

use App\Modules\Espetaculos\Http\Resources\ApresentacaoResource;
use App\Modules\Financeiro\Http\Resources\ContaResource;
use App\Modules\Instrumentos\Http\Resources\InstrumentoResource;
use App\Modules\Academico\Http\Resources\MatriculaResource;
use App\Modules\Core\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "AlunoResource",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "id_usuario", type: "integer", example: 1),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Aluno bolsista"),
        new OA\Property(property: "usuario", ref: "#/components/schemas/UserResource", nullable: true),
        new OA\Property(property: "matriculas", type: "array", items: new OA\Items(ref: "#/components/schemas/MatriculaResource")),
        new OA\Property(property: "contas", type: "array", items: new OA\Items(ref: "#/components/schemas/ContaResource")),
        new OA\Property(property: "id_lead", type: "integer", nullable: true, example: 1)
    ]
)]
class AlunoResource extends JsonResource
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
            'id_usuario' => $this->id_usuario,
            'id_lead' => $this->id_lead,
            'observacoes' => $this->observacoes,
            'usuario' => new UserResource($this->whenLoaded('usuario')),
            'matriculas' => MatriculaResource::collection($this->whenLoaded('matriculas')),
            'contas' => ContaResource::collection($this->whenLoaded('contas')),
            'instrumentos' => InstrumentoResource::collection($this->whenLoaded('instrumentos')),
            'apresentacoes' => ApresentacaoResource::collection($this->whenLoaded('apresentacoes')),
            'contratosAvulsos' => $this->whenLoaded('contratosAvulsos'),
            'responsaveis' => UserResource::collection($this->whenLoaded('responsaveis', function() {
                return $this->responsaveis->map(function($responsavel) {
                    $user = $responsavel->usuario;
                    $user->responsavel_observacoes = $responsavel->observacoes; // Anexar observação do responsável
                    return $user;
                });
            })),
        ];
    }
}
