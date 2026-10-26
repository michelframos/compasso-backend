<?php

namespace App\Modules\Pessoas\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AlunoAutenticadoResource',
    title: 'AlunoAutenticadoResource',
    description: 'Aluno consultado na área do aluno, sem observações internas da secretaria',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'ID do cadastro de aluno', example: 1),
        new OA\Property(property: 'id_usuario', type: 'integer', example: 10),
        new OA\Property(property: 'nome', type: 'string', example: 'Ana Lima'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'ana@escola.com'),
    ]
)]
class AlunoAutenticadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_usuario' => $this->id_usuario,
            'nome' => $this->usuario?->nome,
            'email' => $this->usuario?->email,
        ];
    }
}
