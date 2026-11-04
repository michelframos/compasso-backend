<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Matricula\ListMatriculasDoAlunoRequest;
use App\Modules\Academico\Http\Resources\MatriculaDoAlunoResource;
use App\Modules\Academico\Http\Resources\MatriculaHistoricoResource;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Queries\ListMatriculasDoAlunoQuery;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlunoMatriculaController extends Controller
{
    public function __construct(private readonly AlunoAlvoResolverPort $alunos) {}

    #[OA\Get(
        path: '/api/aluno/me/matriculas',
        summary: 'Matrículas do aluno, com curso, turma, professor, horários e período',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'encerradas para o histórico; omitido lista as vigentes', schema: new OA\Schema(type: 'string', enum: ['encerradas'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Matrículas do aluno', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/MatriculaDoAlunoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, outra instituição ou sem vínculo'),
            new OA\Response(response: 422, description: 'Filtro inválido ou responsável sem id_aluno'),
        ]
    )]
    public function index(ListMatriculasDoAlunoRequest $request, ListMatriculasDoAlunoQuery $query)
    {
        $aluno = $this->resolverAluno($request);
        Gate::authorize('viewAnyDoAluno', [Matricula::class, $aluno->id]);

        return MatriculaDoAlunoResource::collection(
            $query->build($aluno->id, $request->validated('status') === 'encerradas')->get()
        );
    }

    #[OA\Get(
        path: '/api/aluno/me/matriculas/{matricula}/historico',
        summary: 'Histórico de transferências da matrícula do aluno',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'matricula', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Histórico da matrícula, do mais recente ao mais antigo', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'id_matricula', type: 'integer', example: 4),
                    new OA\Property(property: 'id_turma_origem', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'turma_origem', type: 'object', nullable: true),
                    new OA\Property(property: 'id_turma_destino', type: 'integer', example: 3),
                    new OA\Property(property: 'turma_destino', type: 'object'),
                    new OA\Property(property: 'data_transferencia', type: 'string', example: '2026-10-26 09:00:00'),
                    new OA\Property(property: 'motivo', type: 'string', nullable: true, example: 'Mudança de horário'),
                ])),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Matrícula de outro aluno ou sem vínculo'),
            new OA\Response(response: 404, description: 'Matrícula não encontrada'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function historico(Request $request, Matricula $matricula)
    {
        $aluno = $this->resolverAluno($request);

        if ($matricula->id_aluno !== $aluno->id) {
            throw new HttpException(403, 'Acesso restrito: esta matrícula não é sua.');
        }

        Gate::authorize('view', $matricula);

        $historicos = $matricula->historicos()
            ->with(['turmaOrigem.curso', 'turmaOrigem.nivel', 'turmaDestino.curso', 'turmaDestino.nivel'])
            ->orderByDesc('data_transferencia')
            ->orderByDesc('id')
            ->get();

        return MatriculaHistoricoResource::collection($historicos);
    }

    private function resolverAluno(Request $request)
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;

        return $this->alunos->resolver($request->user(), $idAluno);
    }
}
