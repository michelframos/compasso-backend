<?php

namespace App\Modules\Espetaculos\Http\Resources;

use App\Modules\Espetaculos\Models\ApresentacaoAluno;
use App\Modules\Espetaculos\Models\Ensaio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApresentacaoDoProfessorResource',
    title: 'ApresentacaoDoProfessorResource',
    description: 'Apresentação vista pelo professor, sem valores de figurino. `participantes` e `ensaios` só vêm no detalhe.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'titulo_musica', type: 'string', nullable: true, example: 'Asa Branca'),
        new OA\Property(property: 'ordem_entrada', type: 'integer', nullable: true, example: 4),
        new OA\Property(property: 'duracao_estimada', type: 'string', nullable: true, example: '00:05:00'),
        new OA\Property(property: 'espetaculo', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'titulo', type: 'string'),
            new OA\Property(property: 'data_evento', type: 'string', format: 'date'),
            new OA\Property(property: 'local', type: 'string', nullable: true),
            new OA\Property(property: 'status', type: 'string', enum: ['planejamento', 'ensaios', 'concluido', 'cancelado']),
        ]),
        new OA\Property(property: 'turma', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'descricao', type: 'string', nullable: true),
            new OA\Property(property: 'curso', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string')]),
            new OA\Property(property: 'nivel', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string')]),
            new OA\Property(property: 'minha', type: 'boolean', description: 'Se a turma é do professor autenticado'),
        ]),
        new OA\Property(property: 'total_participantes', type: 'integer', example: 12),
        new OA\Property(property: 'meus_alunos', type: 'integer', description: 'Participantes com matrícula vigente com o professor', example: 5),
        new OA\Property(property: 'proximo_ensaio', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'data', type: 'string', format: 'date'),
            new OA\Property(property: 'hora_inicio', type: 'string'),
            new OA\Property(property: 'hora_termino', type: 'string'),
            new OA\Property(property: 'local', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'ensaios_futuros', type: 'integer', example: 2),
        new OA\Property(property: 'pode_agendar_ensaio', type: 'boolean'),
        new OA\Property(property: 'participantes', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'id_aluno', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string', nullable: true),
            new OA\Property(property: 'meu_aluno', type: 'boolean'),
            new OA\Property(property: 'tamanho_figurino', type: 'string', nullable: true),
            new OA\Property(property: 'recebeu_figurino', type: 'boolean'),
            new OA\Property(property: 'presenca_ensaio_geral', type: 'boolean'),
        ])),
        new OA\Property(property: 'ensaios', type: 'array', items: new OA\Items(ref: '#/components/schemas/EnsaioResource')),
    ]
)]
class ApresentacaoDoProfessorResource extends JsonResource
{
    /** @var Collection<int, int>|null só no detalhe, para marcar os participantes */
    private ?Collection $idsMeusAlunos = null;

    /** @param  Collection<int, int>  $idsMeusAlunos */
    public function comDetalhes(Collection $idsMeusAlunos): static
    {
        $this->idsMeusAlunos = $idsMeusAlunos;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $espetaculo = $this->espetaculo;
        $turma = $this->turma;
        $ensaiosFuturos = $this->ensaios->filter(fn (Ensaio $e) => $e->data->toDateString() >= now()->toDateString());
        $proximo = $ensaiosFuturos->first();

        return [
            'id' => $this->id,
            'titulo_musica' => $this->titulo_musica,
            'ordem_entrada' => $this->ordem_entrada,
            'duracao_estimada' => $this->duracao_estimada,
            'espetaculo' => $espetaculo ? [
                'id' => $espetaculo->id,
                'titulo' => $espetaculo->titulo,
                'data_evento' => substr((string) $espetaculo->data_evento, 0, 10),
                'local' => $espetaculo->local,
                'status' => $espetaculo->status,
            ] : null,
            'turma' => $turma ? [
                'id' => $turma->id,
                'descricao' => $turma->descricao,
                'curso' => $turma->curso ? ['nome' => $turma->curso->nome] : null,
                'nivel' => $turma->nivel ? ['nome' => $turma->nivel->nome] : null,
                'minha' => $request->user()?->professor?->id === $turma->id_professor,
            ] : null,
            'total_participantes' => (int) $this->total_participantes,
            'meus_alunos' => (int) $this->meus_alunos_count,
            'proximo_ensaio' => $proximo ? [
                'id' => $proximo->id,
                'data' => $proximo->data->format('Y-m-d'),
                'hora_inicio' => substr((string) $proximo->hora_inicio, 0, 5),
                'hora_termino' => substr((string) $proximo->hora_termino, 0, 5),
                'local' => $proximo->local,
            ] : null,
            'ensaios_futuros' => $ensaiosFuturos->count(),
            'pode_agendar_ensaio' => (bool) $request->user()?->can('gerenciarEnsaios', $this->resource),
            'participantes' => $this->when($this->idsMeusAlunos !== null, fn () => $this->alunos
                ->map(fn (ApresentacaoAluno $p) => [
                    'id' => $p->id,
                    'id_aluno' => $p->id_aluno,
                    'nome' => $p->aluno?->usuario?->nome,
                    'meu_aluno' => $this->idsMeusAlunos->contains($p->id_aluno),
                    'tamanho_figurino' => $p->tamanho_figurino,
                    'recebeu_figurino' => (bool) $p->recebeu_figurino,
                    'presenca_ensaio_geral' => (bool) $p->presenca_ensaio_geral,
                ])
                ->sortBy([['meu_aluno', 'desc'], ['nome', 'asc']])
                ->values()),
            'ensaios' => $this->when($this->idsMeusAlunos !== null, fn () => EnsaioResource::collection($this->ensaios)),
        ];
    }
}
