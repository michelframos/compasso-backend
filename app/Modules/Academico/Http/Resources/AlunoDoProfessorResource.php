<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\Matricula;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AlunoDoProfessorResource',
    title: 'AlunoDoProfessorResource',
    description: 'Aluno do professor com as matrículas vigentes que o ligam a ele (turmas e cursos com aulas individuais)',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'nome', type: 'string', example: 'Bruno Souza'),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'telefone', type: 'string', nullable: true),
        new OA\Property(property: 'foto_url', type: 'string', nullable: true),
        new OA\Property(property: 'matriculas', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'tipo', type: 'string', enum: ['turma', 'curso'], example: 'curso'),
            new OA\Property(property: 'status', type: 'string', nullable: true, example: 'ativa'),
            new OA\Property(property: 'id_turma', type: 'integer', nullable: true),
            new OA\Property(property: 'turma', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'descricao', type: 'string', nullable: true),
            ]),
            new OA\Property(property: 'curso', type: 'object', nullable: true, description: 'Curso da matrícula (tipo curso) ou da turma', properties: [new OA\Property(property: 'nome', type: 'string')]),
            new OA\Property(property: 'nivel', type: 'object', nullable: true, description: 'Nível da matrícula (tipo curso) ou da turma', properties: [new OA\Property(property: 'nome', type: 'string')]),
        ])),
    ]
)]
class AlunoDoProfessorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $usuario = $this->usuario;

        return [
            'id' => $this->id,
            'nome' => $usuario?->nome,
            'email' => $usuario?->email,
            'telefone' => $usuario?->telefone,
            'foto_url' => $usuario?->fotoUrl(),
            'matriculas' => $this->matriculas->map(fn (Matricula $m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'status' => $m->status,
                'id_turma' => $m->id_turma,
                'turma' => $m->turma ? ['id' => $m->turma->id, 'descricao' => $m->turma->descricao] : null,
                'curso' => ($curso = $m->curso ?? $m->turma?->curso) ? ['nome' => $curso->nome] : null,
                'nivel' => ($nivel = $m->nivel ?? $m->turma?->nivel) ? ['nome' => $nivel->nome] : null,
            ])->values()->all(),
        ];
    }
}
