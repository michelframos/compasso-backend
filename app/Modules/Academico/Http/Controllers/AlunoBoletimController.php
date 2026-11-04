<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Boletim\ListBoletimAlunoRequest;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Queries\BoletimAlunoQuery;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlunoBoletimController extends Controller
{
    public function __construct(
        private readonly AlunoAlvoResolverPort $alunos,
        private readonly BoletimAlunoQuery $boletim,
    ) {}

    #[OA\Get(
        path: '/api/aluno/me/boletim',
        summary: 'Boletim do aluno: nível atual, avaliações e feedback liberado ao responsável',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_matricula', in: 'query', required: false, description: 'Restringe o boletim a uma matrícula', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Matrículas com nível, avaliações e observações públicas',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'matriculas', type: 'array', items: new OA\Items(type: 'object')),
                    ]),
                ])
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, outra instituição, sem vínculo ou matrícula de outro aluno'),
            new OA\Response(response: 404, description: 'Matrícula não encontrada'),
            new OA\Response(response: 422, description: 'Filtro inválido ou responsável sem id_aluno'),
        ]
    )]
    public function index(ListBoletimAlunoRequest $request): JsonResponse
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
            'data' => $this->boletim->montar($aluno->id, $idMatricula),
        ]);
    }
}
