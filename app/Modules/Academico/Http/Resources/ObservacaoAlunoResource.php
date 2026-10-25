<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\ObservacaoAluno;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ObservacaoAlunoResource',
    title: 'ObservacaoAlunoResource',
    description: 'Observação pedagógica registrada por um professor sobre um aluno',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'id_aluno', type: 'integer', example: 5),
        new OA\Property(property: 'id_professor', type: 'integer', example: 3),
        new OA\Property(property: 'id_turma', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'tipo', type: 'string', enum: ObservacaoAluno::TIPOS, example: 'evolucao'),
        new OA\Property(property: 'texto', type: 'string', example: 'Evoluiu bem na leitura de partitura.'),
        new OA\Property(property: 'visivel_responsavel', type: 'boolean', example: false),
        new OA\Property(property: 'pode_editar', type: 'boolean', description: 'Se o usuário autenticado pode editar/excluir (autor da observação)', example: true),
        new OA\Property(property: 'professor', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 3),
            new OA\Property(property: 'nome', type: 'string', example: 'Ana Lima'),
        ]),
        new OA\Property(property: 'turma', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 2),
            new OA\Property(property: 'descricao', type: 'string', nullable: true),
            new OA\Property(property: 'curso', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string', example: 'Piano')]),
            new OA\Property(property: 'nivel', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string', example: 'Básico')]),
        ]),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class ObservacaoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $turma = $this->turma;

        return [
            'id' => $this->id,
            'id_aluno' => $this->id_aluno,
            'id_professor' => $this->id_professor,
            'id_turma' => $this->id_turma,
            'tipo' => $this->tipo,
            'texto' => $this->texto,
            'visivel_responsavel' => (bool) $this->visivel_responsavel,
            'pode_editar' => (bool) $request->user()?->can('update', $this->resource),
            'professor' => $this->professor ? [
                'id' => $this->professor->id,
                'nome' => $this->professor->usuario?->nome,
            ] : null,
            'turma' => $turma ? [
                'id' => $turma->id,
                'descricao' => $turma->descricao,
                'curso' => $turma->curso ? ['nome' => $turma->curso->nome] : null,
                'nivel' => $turma->nivel ? ['nome' => $turma->nivel->nome] : null,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
