<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\Matricula;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MatriculaDoAlunoResource',
    title: 'MatriculaDoAlunoResource',
    description: 'Matrícula vista pelo aluno ou responsável, sem observações internas nem valores',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'tipo', type: 'string', enum: ['turma', 'curso'], example: 'turma'),
        new OA\Property(property: 'status', type: 'string', nullable: true, example: 'ativa'),
        new OA\Property(property: 'data', type: 'string', format: 'date', example: '2026-09-01'),
        new OA\Property(property: 'curso', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'nome', type: 'string', example: 'Violão'),
        ]),
        new OA\Property(property: 'nivel', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 1),
            new OA\Property(property: 'nome', type: 'string', example: 'Básico'),
        ]),
        new OA\Property(property: 'turma', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 3),
            new OA\Property(property: 'nome', type: 'string', example: 'Turma Ana'),
            new OA\Property(property: 'status', type: 'string', example: 'em_andamento'),
        ]),
        new OA\Property(property: 'professor', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'integer', example: 2),
            new OA\Property(property: 'nome', type: 'string', example: 'Paula Prof'),
        ]),
        new OA\Property(property: 'horarios', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'dia_semana', type: 'string', example: 'terca'),
            new OA\Property(property: 'hora_inicio', type: 'string', example: '14:00'),
            new OA\Property(property: 'hora_termino', type: 'string', example: '15:00'),
        ])),
        new OA\Property(property: 'periodo', type: 'object', properties: [
            new OA\Property(property: 'inicio', type: 'string', format: 'date', nullable: true, example: '2026-09-01'),
            new OA\Property(property: 'fim', type: 'string', format: 'date', nullable: true, example: '2026-12-15'),
        ]),
    ]
)]
class MatriculaDoAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Matricula $matricula */
        $matricula = $this->resource;
        $turma = $matricula->turma;
        $curso = $turma?->curso ?? $matricula->curso;
        $nivel = $turma?->nivel ?? $matricula->nivel;
        $professor = $turma?->professor ?? $matricula->professor;
        $statusTurma = $turma?->status;

        return [
            'id' => $matricula->id,
            'tipo' => $matricula->tipo,
            'status' => $matricula->status,
            'data' => $this->dataCurta($matricula->data),
            'curso' => $curso ? ['id' => $curso->id, 'nome' => $curso->nome] : null,
            'nivel' => $nivel ? ['id' => $nivel->id, 'nome' => $nivel->nome] : null,
            'turma' => $turma ? [
                'id' => $turma->id,
                'nome' => $turma->apelido(),
                'status' => $statusTurma instanceof \BackedEnum ? $statusTurma->value : $statusTurma,
            ] : null,
            'professor' => $professor ? [
                'id' => $professor->id,
                'nome' => $professor->usuario?->nome,
            ] : null,
            'horarios' => $turma
                ? $turma->horarios
                    ->sortBy('hora_inicio')
                    ->map(fn ($horario) => [
                        'dia_semana' => $horario->dia_semana,
                        'hora_inicio' => substr((string) $horario->hora_inicio, 0, 5),
                        'hora_termino' => substr((string) $horario->hora_termino, 0, 5),
                    ])
                    ->values()
                : [],
            'periodo' => [
                'inicio' => $this->dataCurta($turma?->data_inicio ?? $matricula->data),
                'fim' => $this->dataCurta($turma?->data_fim),
            ],
        ];
    }

    private function dataCurta(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        return substr((string) $valor, 0, 10);
    }
}
