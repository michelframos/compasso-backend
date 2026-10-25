<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\AvisoTurmaDestinatario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AvisoTurmaResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'titulo', type: 'string'),
        new OA\Property(property: 'mensagem', type: 'string'),
        new OA\Property(property: 'publico', type: 'string', enum: ['alunos', 'responsaveis', 'ambos']),
        new OA\Property(property: 'canais', type: 'array', items: new OA\Items(type: 'string', enum: ['email', 'whatsapp'])),
        new OA\Property(property: 'origem', type: 'string', enum: ['manual', 'aula_alterada'], description: 'aula_alterada: enviado ao cancelar, repor ou trocar o professor de uma aula'),
        new OA\Property(property: 'turma', type: 'object', nullable: true, description: 'Nulo quando o aviso foi para alunos escolhidos', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
        ]),
        new OA\Property(property: 'professor', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
        ]),
        new OA\Property(property: 'autor', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
            new OA\Property(property: 'role', type: 'string'),
        ]),
        new OA\Property(property: 'id_aula_turma', type: 'integer', nullable: true),
        new OA\Property(property: 'enviado_em', type: 'string', format: 'date-time'),
        new OA\Property(property: 'totais', type: 'object', description: 'Presente na listagem e no detalhe', properties: [
            new OA\Property(property: 'alunos', type: 'integer'),
            new OA\Property(property: 'destinatarios', type: 'integer', description: 'Pessoa × canal'),
            new OA\Property(property: 'enviados', type: 'integer'),
            new OA\Property(property: 'pendentes', type: 'integer'),
            new OA\Property(property: 'erros', type: 'integer'),
            new OA\Property(property: 'sem_contato', type: 'integer'),
        ]),
        new OA\Property(property: 'destinatarios', type: 'array', description: 'Somente no detalhe', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'nome', type: 'string'),
            new OA\Property(property: 'tipo', type: 'string', enum: ['aluno', 'responsavel']),
            new OA\Property(property: 'aluno', type: 'string', nullable: true, description: 'Aluno pelo qual o responsável foi avisado'),
            new OA\Property(property: 'canal', type: 'string', enum: ['email', 'whatsapp']),
            new OA\Property(property: 'destino', type: 'string', nullable: true),
            new OA\Property(property: 'status', type: 'string', enum: ['pendente', 'enviado', 'erro', 'sem_contato']),
            new OA\Property(property: 'erro', type: 'string', nullable: true),
            new OA\Property(property: 'enviado_em', type: 'string', format: 'date-time', nullable: true),
        ])),
    ]
)]
class AvisoTurmaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'mensagem' => $this->mensagem,
            'publico' => $this->publico,
            'canais' => $this->canais ?? [],
            'origem' => $this->origem,
            'turma' => $this->turma ? ['id' => $this->turma->id, 'nome' => $this->turma->apelido()] : null,
            'professor' => $this->professor ? ['id' => $this->professor->id, 'nome' => $this->professor->usuario?->nome] : null,
            'autor' => ['id' => $this->autor?->id, 'nome' => $this->autor?->nome, 'role' => $this->autor?->role],
            'id_aula_turma' => $this->id_aula_turma,
            'enviado_em' => $this->enviado_em?->toIso8601String(),
            'totais' => $this->when(isset($this->total_destinatarios), fn () => [
                'alunos' => (int) $this->total_alunos,
                'destinatarios' => (int) $this->total_destinatarios,
                'enviados' => (int) $this->total_enviados,
                'pendentes' => (int) $this->total_pendentes,
                'erros' => (int) $this->total_erros,
                'sem_contato' => (int) $this->total_sem_contato,
            ]),
            'destinatarios' => $this->whenLoaded('destinatarios', fn () => $this->destinatarios->map(fn (AvisoTurmaDestinatario $d) => [
                'id' => $d->id,
                'nome' => $d->nome,
                'tipo' => $d->tipo,
                'aluno' => $d->tipo === AvisoTurmaDestinatario::TIPO_RESPONSAVEL ? $d->aluno?->usuario?->nome : null,
                'canal' => $d->canal,
                'destino' => $d->destino,
                'status' => $d->status,
                'erro' => $d->erro,
                'enviado_em' => $d->enviado_em?->toIso8601String(),
            ])),
        ];
    }
}
