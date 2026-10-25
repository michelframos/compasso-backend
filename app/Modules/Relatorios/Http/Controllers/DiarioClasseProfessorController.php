<?php

namespace App\Modules\Relatorios\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Models\Turma;
use App\Modules\Relatorios\Http\Requests\DiarioClasseProfessorRequest;
use App\Modules\Relatorios\Services\DiarioClasseService;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class DiarioClasseProfessorController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me/turmas/{turma}/diario',
        summary: 'Diário de classe de uma turma do professor (aulas concluídas, presenças e conteúdos)',
        security: [['sanctum' => []]],
        tags: ['Relatorios'],
        parameters: [
            new OA\Parameter(name: 'turma', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'data_inicio', in: 'query', required: false, description: 'Padrão: início do mês atual', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'data_fim', in: 'query', required: false, description: 'Padrão: fim do mês atual', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Diário de classe', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'descricao', type: 'string', nullable: true),
                    new OA\Property(property: 'curso', type: 'string', example: 'Violão'),
                    new OA\Property(property: 'nivel', type: 'string', example: 'Básico'),
                    new OA\Property(property: 'professor', type: 'string', example: 'Ana Lima'),
                    new OA\Property(property: 'data_inicio', type: 'string', format: 'date'),
                    new OA\Property(property: 'data_fim', type: 'string', format: 'date'),
                    new OA\Property(property: 'horarios', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'aulas', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'data', type: 'string', format: 'date'),
                        new OA\Property(property: 'hora_inicio', type: 'string'),
                        new OA\Property(property: 'conteudo_dado', type: 'string', nullable: true),
                        new OA\Property(property: 'presencas', type: 'object', description: 'Mapa id_aluno => presente|falta|falta_justificada'),
                    ])),
                    new OA\Property(property: 'alunos', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id_aluno', type: 'integer'),
                        new OA\Property(property: 'nome', type: 'string'),
                        new OA\Property(property: 'data_matricula', type: 'string', format: 'date', nullable: true),
                        new OA\Property(property: 'status', type: 'string'),
                    ])),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Turma de outro professor'),
            new OA\Response(response: 404, description: 'Turma não encontrada'),
            new OA\Response(response: 422, description: 'Período inválido'),
        ]
    )]
    public function show(DiarioClasseProfessorRequest $request, Turma $turma, DiarioClasseService $diarioClasse)
    {
        Gate::authorize('view', $turma);

        $dataInicio = $request->validated('data_inicio') ?? now()->startOfMonth()->toDateString();
        $dataFim = $request->validated('data_fim') ?? now()->endOfMonth()->toDateString();

        return response()->json(['data' => $diarioClasse->montar($turma, $dataInicio, $dataFim)]);
    }
}
