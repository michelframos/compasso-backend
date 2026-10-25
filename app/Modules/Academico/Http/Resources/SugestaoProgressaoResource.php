<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Academico\Models\Turma;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SugestaoProgressaoResource',
    title: 'SugestaoProgressaoResource',
    description: 'Sugestão de progressão de nível feita por um professor',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'status', type: 'string', enum: SugestaoProgressao::STATUS, example: 'pendente'),
        new OA\Property(property: 'justificativa', type: 'string'),
        new OA\Property(property: 'motivo_decisao', type: 'string', nullable: true),
        new OA\Property(property: 'decidido_em', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'pode_cancelar', type: 'boolean', description: 'Autor da sugestão e ainda pendente'),
        new OA\Property(property: 'aluno', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
        ]),
        new OA\Property(property: 'matricula', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'tipo', type: 'string', enum: ['turma', 'curso']),
            new OA\Property(property: 'status', type: 'string', nullable: true),
            new OA\Property(property: 'curso', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string')]),
            new OA\Property(property: 'turma', type: 'object', nullable: true, description: 'Turma atual da matrícula', properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'descricao', type: 'string', nullable: true),
            ]),
        ]),
        new OA\Property(property: 'nivel_atual', type: 'object', nullable: true, description: 'Nível no momento da sugestão', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
        ]),
        new OA\Property(property: 'nivel_sugerido', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
        ]),
        new OA\Property(property: 'professor', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
        ]),
        new OA\Property(property: 'turma_destino', type: 'object', nullable: true, description: 'Turma para a qual o aluno foi transferido na aprovação', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'descricao', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'decisor', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string')]),
        new OA\Property(property: 'turmas_destino', type: 'array', nullable: true, description: 'Somente na listagem da secretaria, para sugestões pendentes de matrícula em turma', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'descricao', type: 'string', nullable: true),
            new OA\Property(property: 'curso', type: 'string', nullable: true),
            new OA\Property(property: 'nivel', type: 'string', nullable: true),
            new OA\Property(property: 'professor', type: 'string', nullable: true),
            new OA\Property(property: 'alunos_vigentes', type: 'integer'),
            new OA\Property(property: 'maximo_alunos', type: 'integer'),
        ])),
    ]
)]
class SugestaoProgressaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $matricula = $this->matricula;
        $curso = $matricula?->curso ?? $matricula?->turma?->curso;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'justificativa' => $this->justificativa,
            'motivo_decisao' => $this->motivo_decisao,
            'decidido_em' => $this->decidido_em?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'pode_cancelar' => $this->estaPendente() && (bool) $request->user()?->can('delete', $this->resource),
            'aluno' => $matricula?->aluno ? [
                'id' => $matricula->aluno->id,
                'nome' => $matricula->aluno->usuario?->nome,
            ] : null,
            'matricula' => $matricula ? [
                'id' => $matricula->id,
                'tipo' => $matricula->tipo,
                'status' => $matricula->status,
                'curso' => $curso ? ['nome' => $curso->nome] : null,
                'turma' => $matricula->turma ? ['id' => $matricula->turma->id, 'descricao' => $matricula->turma->descricao] : null,
            ] : null,
            'nivel_atual' => $this->nivelAtual ? ['id' => $this->nivelAtual->id, 'nome' => $this->nivelAtual->nome] : null,
            'nivel_sugerido' => $this->nivelSugerido ? ['id' => $this->nivelSugerido->id, 'nome' => $this->nivelSugerido->nome] : null,
            'professor' => $this->professor ? ['id' => $this->professor->id, 'nome' => $this->professor->usuario?->nome] : null,
            'turma_destino' => $this->turmaDestino ? [
                'id' => $this->turmaDestino->id,
                'descricao' => $this->turmaDestino->descricao,
            ] : null,
            'decisor' => $this->decisor ? ['nome' => $this->decisor->nome] : null,
            'turmas_destino' => $this->when(
                $this->resource->relationLoaded('turmasDisponiveis'),
                fn () => $this->resource->getRelation('turmasDisponiveis')->map(fn (Turma $t) => [
                    'id' => $t->id,
                    'descricao' => $t->descricao,
                    'curso' => $t->curso?->nome,
                    'nivel' => $t->nivel?->nome,
                    'professor' => $t->professor?->usuario?->nome,
                    'alunos_vigentes' => (int) $t->alunos_vigentes_count,
                    'maximo_alunos' => (int) $t->maximo_alunos,
                ])->values()->all()
            ),
        ];
    }
}
