<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\MaterialTurma\ListMateriaisDoAlunoRequest;
use App\Modules\Academico\Http\Resources\MaterialDoAlunoResource;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Queries\ListMateriaisDoAlunoQuery;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlunoMaterialController extends Controller
{
    public function __construct(private readonly AlunoAlvoResolverPort $alunos) {}

    #[OA\Get(
        path: '/api/aluno/me/materiais',
        summary: 'Materiais públicos das turmas do aluno',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_turma', in: 'query', required: false, description: 'Restringe a uma turma do aluno', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Materiais públicos, do mais recente ao mais antigo', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/MaterialDoAlunoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, outra instituição, sem vínculo ou turma de outro aluno'),
            new OA\Response(response: 404, description: 'Turma não encontrada'),
            new OA\Response(response: 422, description: 'Filtro inválido ou responsável sem id_aluno'),
        ]
    )]
    public function index(ListMateriaisDoAlunoRequest $request, ListMateriaisDoAlunoQuery $query)
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;
        $aluno = $this->alunos->resolver($request->user(), $idAluno);
        Gate::authorize('viewAnyDoAluno', [MaterialTurma::class, $aluno->id]);

        $idTurma = $request->filled('id_turma') ? $request->integer('id_turma') : null;

        if ($idTurma !== null) {
            if (Turma::query()->whereKey($idTurma)->doesntExist()) {
                abort(404);
            }

            $matriculado = Matricula::query()
                ->where('id_aluno', $aluno->id)
                ->where('id_turma', $idTurma)
                ->exists();

            if (! $matriculado) {
                throw new HttpException(403, 'Acesso restrito: você não está matriculado nesta turma.');
            }
        }

        return MaterialDoAlunoResource::collection(
            $query->build($request->user(), $aluno->id, $idTurma)->get()
        );
    }
}
