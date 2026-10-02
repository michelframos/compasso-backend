<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AlunoDaTurmaResource',
    title: 'AlunoDaTurmaResource',
    description: 'Matrícula de um aluno na turma, com os dados de contato visíveis ao professor',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'ID da matrícula', example: 1),
        new OA\Property(property: 'id_aluno', type: 'integer', example: 5),
        new OA\Property(property: 'id_turma', type: 'integer', example: 2),
        new OA\Property(property: 'status', type: 'string', example: 'ativa'),
        new OA\Property(property: 'data', type: 'string', format: 'date', nullable: true, example: '2026-02-01'),
        new OA\Property(property: 'aluno', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 5),
            new OA\Property(property: 'usuario', type: 'object', properties: [
                new OA\Property(property: 'nome', type: 'string', example: 'Bruno Souza'),
                new OA\Property(property: 'email', type: 'string', nullable: true, example: 'bruno@email.com'),
                new OA\Property(property: 'telefone', type: 'string', nullable: true),
                new OA\Property(property: 'foto', type: 'string', nullable: true),
            ]),
        ]),
    ]
)]
class AlunoDaTurmaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $usuario = $this->aluno?->usuario;

        return [
            'id' => $this->id,
            'id_aluno' => $this->id_aluno,
            'id_turma' => $this->id_turma,
            'status' => $this->status,
            'data' => $this->data,
            'aluno' => $this->aluno ? [
                'id' => $this->aluno->id,
                'usuario' => [
                    'nome' => $usuario?->nome,
                    'email' => $usuario?->email,
                    'telefone' => $usuario?->telefone,
                    'foto' => $usuario?->foto,
                ],
            ] : null,
        ];
    }
}
