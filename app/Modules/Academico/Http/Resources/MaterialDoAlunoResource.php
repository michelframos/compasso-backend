<?php

namespace App\Modules\Academico\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MaterialDoAlunoResource',
    title: 'Material do aluno',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'titulo', type: 'string', example: 'Partitura da Ana'),
        new OA\Property(property: 'descricao', type: 'string', nullable: true, example: 'Primeiras lições'),
        new OA\Property(property: 'tipo', type: 'string', enum: ['arquivo', 'link'], example: 'link'),
        new OA\Property(property: 'file_url', type: 'string', nullable: true),
        new OA\Property(property: 'file_type', type: 'string', nullable: true, example: 'audio/mpeg'),
        new OA\Property(property: 'link', type: 'string', nullable: true, example: 'https://exemplo.test/ana'),
        new OA\Property(property: 'turma', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'nome', type: 'string', example: 'Turma Ana'),
            new OA\Property(property: 'curso', type: 'string', nullable: true, example: 'Violão'),
        ]),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class MaterialDoAlunoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'tipo' => $this->link ? 'link' : 'arquivo',
            'file_url' => $this->file_path ? url('storage/'.$this->file_path) : null,
            'file_type' => $this->file_type,
            'link' => $this->link,
            'turma' => [
                'id' => $this->turma?->id,
                'nome' => $this->turma?->apelido() ?? 'Turma',
                'curso' => $this->turma?->curso?->nome,
            ],
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
