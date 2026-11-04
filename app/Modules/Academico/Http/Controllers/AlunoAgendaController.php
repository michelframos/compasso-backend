<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Agenda\ListAgendaAlunoRequest;
use App\Modules\Academico\Queries\AgendaAlunoQuery;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AlunoAgendaController extends Controller
{
    public function __construct(
        private readonly AlunoAlvoResolverPort $alunos,
        private readonly AgendaAlunoQuery $agenda,
    ) {}

    #[OA\Get(
        path: '/api/aluno/me/agenda',
        summary: 'Agenda do aluno no período: aulas, remarcações, apresentações e ensaios',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'inicio', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-10-26')),
            new OA\Parameter(name: 'fim', in: 'query', required: true, description: 'No máximo 45 dias depois de inicio', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-11-01')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Compromissos do período, em ordem cronológica',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'inicio', type: 'string', format: 'date'),
                        new OA\Property(property: 'fim', type: 'string', format: 'date'),
                        new OA\Property(property: 'espetaculos', type: 'boolean', description: 'Falso quando o plano não inclui o módulo'),
                        new OA\Property(property: 'itens', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'tipo', type: 'string', enum: ['aula', 'apresentacao', 'ensaio']),
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'data', type: 'string', format: 'date'),
                            new OA\Property(property: 'hora_inicio', type: 'string', nullable: true, example: '14:00'),
                            new OA\Property(property: 'hora_termino', type: 'string', nullable: true, example: '15:00'),
                            new OA\Property(property: 'titulo', type: 'string', example: 'Turma Ana'),
                            new OA\Property(property: 'detalhe', type: 'string', nullable: true),
                            new OA\Property(property: 'status', type: 'string', nullable: true, example: 'agendada'),
                            new OA\Property(property: 'remarcada', type: 'boolean'),
                            new OA\Property(property: 'remarcacao', type: 'object', nullable: true, properties: [
                                new OA\Property(property: 'de', type: 'object', nullable: true),
                                new OA\Property(property: 'para', type: 'object', nullable: true),
                            ]),
                        ])),
                    ]),
                ])
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, outra instituição ou sem vínculo'),
            new OA\Response(response: 422, description: 'Período inválido ou responsável sem id_aluno'),
        ]
    )]
    public function index(ListAgendaAlunoRequest $request): JsonResponse
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;
        $aluno = $this->alunos->resolver($request->user(), $idAluno);

        return response()->json([
            'data' => $this->agenda->montar($aluno->id, $request->validated('inicio'), $request->validated('fim')),
        ]);
    }
}
