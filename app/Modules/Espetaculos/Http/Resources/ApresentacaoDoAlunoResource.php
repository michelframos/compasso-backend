<?php

namespace App\Modules\Espetaculos\Http\Resources;

use App\Modules\Espetaculos\Models\Ensaio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApresentacaoDoAlunoResource',
    title: 'ApresentacaoDoAlunoResource',
    description: 'Apresentação em que o aluno está escalado. Sem valores de figurino, observações internas ou outros participantes.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'titulo_musica', type: 'string', nullable: true, example: 'Asa Branca'),
        new OA\Property(property: 'ordem_entrada', type: 'integer', nullable: true, example: 4),
        new OA\Property(property: 'duracao_estimada', type: 'string', nullable: true, example: '00:05:00'),
        new OA\Property(property: 'papel', type: 'string', nullable: true, example: 'Solista'),
        new OA\Property(property: 'tamanho_figurino', type: 'string', nullable: true, example: 'P'),
        new OA\Property(property: 'recebeu_figurino', type: 'boolean'),
        new OA\Property(property: 'espetaculo', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'titulo', type: 'string'),
            new OA\Property(property: 'data_evento', type: 'string', format: 'date'),
            new OA\Property(property: 'local', type: 'string', nullable: true),
            new OA\Property(property: 'status', type: 'string', enum: ['planejamento', 'ensaios', 'concluido', 'cancelado']),
        ]),
        new OA\Property(property: 'turma', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'ensaios', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'data', type: 'string', format: 'date'),
            new OA\Property(property: 'hora_inicio', type: 'string', example: '10:00'),
            new OA\Property(property: 'hora_termino', type: 'string', example: '11:00'),
            new OA\Property(property: 'local', type: 'string', nullable: true),
        ])),
    ]
)]
class ApresentacaoDoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participacao = $this->alunos->first();
        $espetaculo = $this->espetaculo;
        $turma = $this->turma;

        return [
            'id' => $this->id,
            'titulo_musica' => $this->titulo_musica,
            'ordem_entrada' => $this->ordem_entrada,
            'duracao_estimada' => $this->duracao_estimada ? substr((string) $this->duracao_estimada, 0, 8) : null,
            'papel' => $participacao?->papel,
            'tamanho_figurino' => $participacao?->tamanho_figurino,
            'recebeu_figurino' => (bool) $participacao?->recebeu_figurino,
            'espetaculo' => $espetaculo ? [
                'id' => $espetaculo->id,
                'titulo' => $espetaculo->titulo,
                'data_evento' => substr((string) $espetaculo->data_evento, 0, 10),
                'local' => $espetaculo->local,
                'status' => $espetaculo->status,
            ] : null,
            'turma' => $turma ? [
                'id' => $turma->id,
                'nome' => $turma->descricao,
            ] : null,
            'ensaios' => $this->ensaios->map(fn (Ensaio $ensaio) => [
                'id' => $ensaio->id,
                'data' => $ensaio->data->format('Y-m-d'),
                'hora_inicio' => substr((string) $ensaio->hora_inicio, 0, 5),
                'hora_termino' => substr((string) $ensaio->hora_termino, 0, 5),
                'local' => $ensaio->local,
            ])->values(),
        ];
    }
}
