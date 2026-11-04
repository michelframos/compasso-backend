<?php

namespace App\Modules\Espetaculos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use App\Modules\Espetaculos\Http\Requests\Apresentacao\ListApresentacoesDoAlunoRequest;
use App\Modules\Espetaculos\Http\Resources\ApresentacaoDoAlunoResource;
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Queries\ListApresentacoesDoAlunoQuery;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class AlunoApresentacaoController extends Controller
{
    public function __construct(private readonly AlunoAlvoResolverPort $alunos) {}

    #[OA\Get(
        path: '/api/aluno/me/apresentacoes',
        summary: 'Apresentações em que o aluno está escalado, com data, local e figurino',
        security: [['sanctum' => []]],
        tags: ['Espetaculos'],
        parameters: [
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Apresentações por data do espetáculo', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ApresentacaoDoAlunoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, sem vínculo, outra instituição ou módulo de espetáculos indisponível'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function index(ListApresentacoesDoAlunoRequest $request, ListApresentacoesDoAlunoQuery $query)
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;
        $aluno = $this->alunos->resolver($request->user(), $idAluno);
        Gate::authorize('viewAnyDoAluno', [Apresentacao::class, $aluno->id]);

        return ApresentacaoDoAlunoResource::collection($query->build($aluno->id)->get());
    }
}
