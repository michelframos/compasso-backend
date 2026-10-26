<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Frequencia\ListFrequenciaAlunoRequest;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Queries\FrequenciaAlunoQuery;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlunoFrequenciaController extends Controller
{
    public function __construct(
        private readonly AlunoAlvoResolverPort $alunos,
        private readonly FrequenciaAlunoQuery $frequencia,
    ) {}

    #[OA\Get(
        path: '/api/aluno/me/frequencia',
        summary: 'Frequência do aluno: presenças, faltas, justificadas e percentual por turma',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_matricula', in: 'query', required: false, description: 'Restringe o resumo a uma matrícula', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resumo geral e a chamada de cada turma, da aula mais recente para a mais antiga',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'resumo', type: 'object', properties: [
                            new OA\Property(property: 'total', type: 'integer', example: 4),
                            new OA\Property(property: 'presentes', type: 'integer', example: 2),
                            new OA\Property(property: 'ausentes', type: 'integer', example: 1),
                            new OA\Property(property: 'justificados', type: 'integer', example: 1),
                            new OA\Property(property: 'percentual', type: 'number', nullable: true, example: 50, description: 'Presenças sobre o total. Falta justificada não conta como presença.'),
                        ]),
                        new OA\Property(property: 'turmas', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'id_matricula', type: 'integer', nullable: true),
                            new OA\Property(property: 'id_turma', type: 'integer', nullable: true),
                            new OA\Property(property: 'nome', type: 'string', example: 'Turma Ana'),
                            new OA\Property(property: 'curso', type: 'string', nullable: true, example: 'Violão'),
                            new OA\Property(property: 'status_matricula', type: 'string', nullable: true, example: 'ativa'),
                            new OA\Property(property: 'total', type: 'integer'),
                            new OA\Property(property: 'presentes', type: 'integer'),
                            new OA\Property(property: 'ausentes', type: 'integer'),
                            new OA\Property(property: 'justificados', type: 'integer'),
                            new OA\Property(property: 'percentual', type: 'number', nullable: true),
                            new OA\Property(property: 'aulas', type: 'array', items: new OA\Items(properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'id_aula', type: 'integer'),
                                new OA\Property(property: 'data', type: 'string', format: 'date'),
                                new OA\Property(property: 'hora_inicio', type: 'string', example: '14:00'),
                                new OA\Property(property: 'hora_termino', type: 'string', example: '15:00'),
                                new OA\Property(property: 'status', type: 'string', enum: ['presente', 'ausente', 'justificado']),
                            ])),
                        ])),
                    ]),
                ])
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, outra instituição, sem vínculo ou matrícula de outro aluno'),
            new OA\Response(response: 404, description: 'Matrícula não encontrada'),
            new OA\Response(response: 422, description: 'Filtro inválido ou responsável sem id_aluno'),
        ]
    )]
    public function index(ListFrequenciaAlunoRequest $request): JsonResponse
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;
        $aluno = $this->alunos->resolver($request->user(), $idAluno);
        Gate::authorize('viewAnyDoAluno', [Matricula::class, $aluno->id]);
        $idMatricula = $request->filled('id_matricula') ? $request->integer('id_matricula') : null;

        if ($idMatricula !== null) {
            $matricula = Matricula::query()->find($idMatricula);

            if ($matricula === null) {
                abort(404);
            }

            if ($matricula->id_aluno !== $aluno->id) {
                throw new HttpException(403, 'Acesso restrito: esta matrícula não é sua.');
            }

            Gate::authorize('view', $matricula);
        }

        return response()->json([
            'data' => $this->frequencia->montar($aluno->id, $idMatricula),
        ]);
    }
}
