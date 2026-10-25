<?php

namespace App\Modules\Academico\Http\Resources;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Pessoas\Models\Responsavel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'FichaAlunoResource',
    title: 'FichaAlunoResource',
    description: 'Ficha do aluno vista pelo professor: dados básicos, matrículas nas turmas do professor, frequência nas suas aulas e observações pedagógicas',
    properties: [
        new OA\Property(property: 'aluno', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'integer', example: 5),
            new OA\Property(property: 'nome', type: 'string', example: 'Bruno Souza'),
            new OA\Property(property: 'email', type: 'string', nullable: true),
            new OA\Property(property: 'telefone', type: 'string', nullable: true),
            new OA\Property(property: 'whatsapp', type: 'string', nullable: true),
            new OA\Property(property: 'foto_url', type: 'string', nullable: true),
            new OA\Property(property: 'data_nascimento', type: 'string', format: 'date', nullable: true, example: '2012-04-10'),
            new OA\Property(property: 'idade', type: 'integer', nullable: true, example: 14),
            new OA\Property(property: 'responsaveis', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'nome', type: 'string'),
                new OA\Property(property: 'parentesco', type: 'string', nullable: true, example: 'Mãe'),
                new OA\Property(property: 'telefone', type: 'string', nullable: true),
                new OA\Property(property: 'whatsapp', type: 'string', nullable: true),
            ])),
        ]),
        new OA\Property(property: 'matriculas', type: 'array', description: 'Matrículas em turmas do professor e matrículas por curso atribuídas a ele', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'tipo', type: 'string', enum: ['turma', 'curso'], example: 'turma'),
            new OA\Property(property: 'id_turma', type: 'integer', nullable: true),
            new OA\Property(property: 'id_curso', type: 'integer', nullable: true, description: 'Curso atual (da matrícula por curso ou da turma)'),
            new OA\Property(property: 'id_nivel', type: 'integer', nullable: true, description: 'Nível atual (da matrícula por curso ou da turma)'),
            new OA\Property(property: 'status', type: 'string', nullable: true, example: 'ativa'),
            new OA\Property(property: 'data', type: 'string', format: 'date', nullable: true),
            new OA\Property(property: 'curso', type: 'object', nullable: true, description: 'Curso da matrícula (tipo curso) ou da turma', properties: [new OA\Property(property: 'nome', type: 'string')]),
            new OA\Property(property: 'nivel', type: 'object', nullable: true, description: 'Nível da matrícula (tipo curso) ou da turma', properties: [new OA\Property(property: 'nome', type: 'string')]),
            new OA\Property(property: 'turma', type: 'object', nullable: true, properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'descricao', type: 'string', nullable: true),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'curso', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string')]),
                new OA\Property(property: 'nivel', type: 'object', nullable: true, properties: [new OA\Property(property: 'nome', type: 'string')]),
            ]),
        ])),
        new OA\Property(property: 'frequencia', type: 'object', description: 'Presenças lançadas nas aulas do professor (aulas canceladas ignoradas)', properties: [
            new OA\Property(property: 'total', type: 'integer', example: 12),
            new OA\Property(property: 'presentes', type: 'integer', example: 10),
            new OA\Property(property: 'ausentes', type: 'integer', example: 1),
            new OA\Property(property: 'justificados', type: 'integer', example: 1),
            new OA\Property(property: 'percentual_presenca', type: 'number', nullable: true, example: 83.3),
            new OA\Property(property: 'por_turma', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'id_turma', type: 'integer', nullable: true, description: 'Nulo = aulas individuais (sem turma)'),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'presentes', type: 'integer'),
                new OA\Property(property: 'ausentes', type: 'integer'),
                new OA\Property(property: 'justificados', type: 'integer'),
                new OA\Property(property: 'percentual_presenca', type: 'number', nullable: true),
            ])),
            new OA\Property(property: 'ultimas', type: 'array', description: 'Últimas 10 presenças, da mais recente para a mais antiga', items: new OA\Items(properties: [
                new OA\Property(property: 'id_aula', type: 'integer'),
                new OA\Property(property: 'id_turma', type: 'integer', nullable: true),
                new OA\Property(property: 'data', type: 'string', format: 'date'),
                new OA\Property(property: 'status', type: 'string', enum: ['presente', 'ausente', 'justificado']),
                new OA\Property(property: 'observacao', type: 'string', nullable: true),
            ])),
        ]),
        new OA\Property(property: 'observacoes', type: 'array', items: new OA\Items(ref: '#/components/schemas/ObservacaoAlunoResource')),
        new OA\Property(property: 'avaliacoes', type: 'array', description: 'Avaliações do aluno (inclusive de colegas), da mais recente para a mais antiga', items: new OA\Items(ref: '#/components/schemas/AvaliacaoAlunoResource')),
        new OA\Property(property: 'progressoes', type: 'array', description: 'Sugestões de progressão das matrículas listadas', items: new OA\Items(ref: '#/components/schemas/SugestaoProgressaoResource')),
    ]
)]
/** Recebe o array montado pelo FichaAlunoService. */
class FichaAlunoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $aluno = $this->resource['aluno'];
        $usuario = $aluno->usuario;
        $nascimento = $this->dataNascimento($usuario?->data_aniversario);

        return [
            'aluno' => [
                'id' => $aluno->id,
                'nome' => $usuario?->nome,
                'email' => $usuario?->email,
                'telefone' => $usuario?->telefone,
                'whatsapp' => $usuario?->whatsapp,
                'foto_url' => $usuario?->fotoUrl(),
                'data_nascimento' => $nascimento?->format('Y-m-d'),
                'idade' => $nascimento?->age,
                'responsaveis' => $aluno->responsaveis->map(fn (Responsavel $r) => [
                    'id' => $r->id,
                    'nome' => $r->usuario?->nome,
                    'parentesco' => $r->pivot?->parentesco,
                    'telefone' => $r->usuario?->telefone,
                    'whatsapp' => $r->usuario?->whatsapp,
                ])->values()->all(),
            ],
            'matriculas' => $this->resource['matriculas']->map(fn (Matricula $m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'id_turma' => $m->id_turma,
                'id_curso' => $m->idCursoAtual(),
                'id_nivel' => $m->idNivelAtual(),
                'status' => $m->status,
                'data' => $m->data?->format('Y-m-d'),
                'curso' => ($curso = $m->curso ?? $m->turma?->curso) ? ['nome' => $curso->nome] : null,
                'nivel' => ($nivel = $m->nivel ?? $m->turma?->nivel) ? ['nome' => $nivel->nome] : null,
                'turma' => $m->turma ? [
                    'id' => $m->turma->id,
                    'descricao' => $m->turma->descricao,
                    'status' => $m->turma->status instanceof \BackedEnum ? $m->turma->status->value : $m->turma->status,
                    'curso' => $m->turma->curso ? ['nome' => $m->turma->curso->nome] : null,
                    'nivel' => $m->turma->nivel ? ['nome' => $m->turma->nivel->nome] : null,
                ] : null,
            ])->values()->all(),
            'frequencia' => $this->resource['frequencia'],
            'observacoes' => ObservacaoAlunoResource::collection($this->resource['observacoes'])->resolve($request),
            'avaliacoes' => AvaliacaoAlunoResource::collection($this->resource['avaliacoes'])->resolve($request),
            'progressoes' => SugestaoProgressaoResource::collection($this->resource['progressoes'])->resolve($request),
        ];
    }

    private function dataNascimento(?string $valor): ?Carbon
    {
        if (! $valor) {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }
}
