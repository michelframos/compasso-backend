<?php

namespace App\Modules\Relatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Models\Turma;
use App\Modules\Relatorios\Http\Requests\FrequenciaProfessorRequest;
use App\Modules\Relatorios\Services\AbsenteismoService;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class FrequenciaProfessorController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me/relatorios/frequencia',
        summary: 'Frequência e alunos em risco nas aulas do professor (turmas e aulas individuais)',
        security: [['sanctum' => []]],
        tags: ['Relatorios'],
        parameters: [
            new OA\Parameter(name: 'data_inicio', in: 'query', required: false, description: 'Padrão: início do mês atual', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'data_fim', in: 'query', required: false, description: 'Padrão: fim do mês atual', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'id_turma', in: 'query', required: false, description: 'Restringe a uma turma do professor', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limite_faltas', in: 'query', required: false, description: 'Faltas consecutivas para considerar o aluno em risco (padrão 3)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Frequência por aluno, em ordem alfabética', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'integer', description: 'ID do aluno', example: 12),
                    new OA\Property(property: 'nome_aluno', type: 'string', example: 'Bruno Souza'),
                    new OA\Property(property: 'total_aulas', type: 'integer', description: 'Aulas com chamada no período', example: 8),
                    new OA\Property(property: 'total_presencas', type: 'integer', example: 5),
                    new OA\Property(property: 'total_faltas', type: 'integer', description: 'Inclui as justificadas', example: 3),
                    new OA\Property(property: 'total_justificadas', type: 'integer', example: 1),
                    new OA\Property(property: 'taxa_absenteismo', type: 'number', format: 'float', example: 37.5),
                    new OA\Property(property: 'faltas_consecutivas', type: 'integer', description: 'Faltas não justificadas seguidas até o fim do período (ou hoje)', example: 2),
                    new OA\Property(property: 'em_risco', type: 'boolean', example: false),
                ])),
                new OA\Property(property: 'summary', type: 'object', properties: [
                    new OA\Property(property: 'total_alunos', type: 'integer'),
                    new OA\Property(property: 'media_absenteismo', type: 'number', format: 'float'),
                    new OA\Property(property: 'alunos_em_risco', type: 'integer'),
                ]),
                new OA\Property(property: 'filters', type: 'object', properties: [
                    new OA\Property(property: 'data_inicio', type: 'string', format: 'date'),
                    new OA\Property(property: 'data_fim', type: 'string', format: 'date'),
                    new OA\Property(property: 'id_turma', type: 'integer', nullable: true),
                    new OA\Property(property: 'limite_faltas', type: 'integer'),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Turma de outro professor'),
            new OA\Response(response: 422, description: 'Filtros inválidos'),
        ]
    )]
    public function show(FrequenciaProfessorRequest $request, AbsenteismoService $absenteismo)
    {
        $idTurma = $request->validated('id_turma');

        if ($idTurma) {
            Gate::authorize('view', Turma::findOrFail($idTurma));
        }

        return response()->json($absenteismo->calcular(
            $request->user(),
            $request->validated('data_inicio') ?? now()->startOfMonth()->toDateString(),
            $request->validated('data_fim') ?? now()->endOfMonth()->toDateString(),
            (int) ($request->validated('limite_faltas') ?? 3),
            $idTurma ? (int) $idTurma : null,
        ));
    }
}
