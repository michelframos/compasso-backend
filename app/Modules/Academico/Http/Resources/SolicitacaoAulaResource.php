<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\AulaTurma;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SolicitacaoAulaResource',
    title: 'SolicitacaoAulaResource',
    description: 'Solicitação do professor para criar, cancelar, repor ou passar uma aula a um substituto',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'tipo', type: 'string', enum: ['criacao', 'cancelamento', 'reposicao', 'substituicao']),
        new OA\Property(property: 'status', type: 'string', enum: ['pendente', 'aprovada', 'rejeitada', 'cancelada']),
        new OA\Property(property: 'aplicada_automaticamente', type: 'boolean', description: 'Aplicada sem aprovação porque a escola deixa a ação livre'),
        new OA\Property(property: 'motivo', type: 'string', nullable: true),
        new OA\Property(property: 'data_sugerida', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'hora_inicio_sugerida', type: 'string', nullable: true, example: '14:00'),
        new OA\Property(property: 'hora_termino_sugerida', type: 'string', nullable: true, example: '15:00'),
        new OA\Property(property: 'destino_cobranca', type: 'string', nullable: true, enum: ['proxima_aula', 'cancelar']),
        new OA\Property(property: 'avisar_alunos', type: 'boolean', description: 'Alunos e responsáveis são avisados quando a alteração é aplicada'),
        new OA\Property(property: 'motivo_decisao', type: 'string', nullable: true),
        new OA\Property(property: 'decidido_em', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'pode_cancelar', type: 'boolean'),
        new OA\Property(property: 'aula', ref: '#/components/schemas/SolicitacaoAulaResumoAula', nullable: true),
        new OA\Property(property: 'aula_gerada', ref: '#/components/schemas/SolicitacaoAulaResumoAula', nullable: true, description: 'Aula criada pela reposição ou criação'),
        new OA\Property(property: 'nova_aula', type: 'object', nullable: true, description: 'Somente na criação', properties: [
            new OA\Property(property: 'tipo_aula', type: 'string', nullable: true),
            new OA\Property(property: 'turma', type: 'object', nullable: true, properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'titulo', type: 'string')]),
            new OA\Property(property: 'curso', type: 'object', nullable: true, properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'nome', type: 'string')]),
            new OA\Property(property: 'aluno', type: 'object', nullable: true, properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'nome', type: 'string')]),
        ]),
        new OA\Property(property: 'professor', type: 'object', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'nome', type: 'string')]),
        new OA\Property(property: 'substituto', type: 'object', nullable: true, properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'nome', type: 'string')]),
        new OA\Property(property: 'decisor', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string')]),
        new OA\Property(property: 'analise', type: 'object', nullable: true, description: 'Somente em solicitações pendentes', properties: [
            new OA\Property(property: 'avisos', type: 'array', items: new OA\Items(type: 'string'), description: 'Conflitos e horários fora da disponibilidade (não impedem a decisão, exceto conflito)'),
            new OA\Property(property: 'tem_cobranca', type: 'boolean'),
            new OA\Property(property: 'proxima_aula_cobranca', type: 'string', nullable: true),
            new OA\Property(property: 'disponibilidade', type: 'array', items: new OA\Items(ref: '#/components/schemas/DisponibilidadeProfessorResource')),
        ]),
    ]
)]
#[OA\Schema(
    schema: 'SolicitacaoAulaResumoAula',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'data', type: 'string', format: 'date'),
        new OA\Property(property: 'hora_inicio', type: 'string', example: '14:00'),
        new OA\Property(property: 'hora_termino', type: 'string', example: '15:00'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'tipo', type: 'string'),
        new OA\Property(property: 'titulo', type: 'string', description: 'Curso e nível da turma, ou "Aula individual"'),
        new OA\Property(property: 'id_turma', type: 'integer', nullable: true),
        new OA\Property(property: 'aluno', type: 'string', nullable: true, description: 'Aluno da aula individual'),
        new OA\Property(property: 'professor', type: 'object', nullable: true, properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'nome', type: 'string')]),
    ]
)]
class SolicitacaoAulaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'status' => $this->status,
            'aplicada_automaticamente' => (bool) $this->aplicada_automaticamente,
            'motivo' => $this->motivo,
            'data_sugerida' => $this->data_sugerida?->toDateString(),
            'hora_inicio_sugerida' => $this->hora($this->hora_inicio_sugerida),
            'hora_termino_sugerida' => $this->hora($this->hora_termino_sugerida),
            'destino_cobranca' => $this->destino_cobranca,
            'avisar_alunos' => (bool) $this->avisar_alunos,
            'motivo_decisao' => $this->motivo_decisao,
            'decidido_em' => $this->decidido_em?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'pode_cancelar' => $this->estaPendente() && (bool) $request->user()?->can('delete', $this->resource),
            'aula' => $this->resumo($this->aula),
            'aula_gerada' => $this->resumo($this->aulaGerada),
            'nova_aula' => $this->tipo === 'criacao' ? [
                'tipo_aula' => $this->tipo_aula,
                'turma' => $this->turma ? ['id' => $this->turma->id, 'titulo' => $this->tituloTurma($this->turma)] : null,
                'curso' => $this->curso ? ['id' => $this->curso->id, 'nome' => $this->curso->nome] : null,
                'aluno' => $this->alunoEspecifico ? ['id' => $this->alunoEspecifico->id, 'nome' => $this->alunoEspecifico->usuario?->nome] : null,
            ] : null,
            'professor' => $this->pessoa($this->professor),
            'substituto' => $this->pessoa($this->substituto),
            'decisor' => $this->decisor ? ['nome' => $this->decisor->nome] : null,
            'analise' => $this->when(
                $this->resource->relationLoaded('analise'),
                fn () => $this->resource->getRelation('analise')->all()
            ),
        ];
    }

    private function resumo(?AulaTurma $aula): ?array
    {
        if ($aula === null) {
            return null;
        }

        return [
            'id' => $aula->id,
            'data' => $aula->data?->toDateString(),
            'hora_inicio' => $this->hora($aula->hora_inicio),
            'hora_termino' => $this->hora($aula->hora_termino),
            'status' => $aula->status,
            'tipo' => $aula->tipo,
            'titulo' => $aula->turma
                ? $this->tituloTurma($aula->turma)
                : trim('Aula individual'.($aula->curso?->nome ? ' de '.$aula->curso->nome : '')),
            'id_turma' => $aula->id_turma,
            'aluno' => $aula->aluno_especifico?->usuario?->nome,
            'professor' => $this->pessoa($aula->professor),
        ];
    }

    private function tituloTurma($turma): string
    {
        return trim(($turma->curso?->nome ?? 'Turma').' '.($turma->nivel?->nome ?? '')) ?: ($turma->descricao ?? 'Turma');
    }

    private function pessoa($professor): ?array
    {
        return $professor ? ['id' => $professor->id, 'nome' => $professor->usuario?->nome] : null;
    }

    private function hora(?string $hora): ?string
    {
        return $hora ? substr($hora, 0, 5) : null;
    }
}
