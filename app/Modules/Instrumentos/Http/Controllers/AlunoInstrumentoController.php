<?php

namespace App\Modules\Instrumentos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use App\Modules\Instrumentos\Http\Requests\ListInstrumentosDoAlunoRequest;
use App\Modules\Instrumentos\Http\Resources\HistoricoInstrumentoDoAlunoResource;
use App\Modules\Instrumentos\Http\Resources\InstrumentoDoAlunoResource;
use App\Modules\Instrumentos\Models\Instrumento;
use App\Modules\Instrumentos\Queries\ListInstrumentosDoAlunoQuery;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class AlunoInstrumentoController extends Controller
{
    public function __construct(private readonly AlunoAlvoResolverPort $alunos) {}

    #[OA\Get(
        path: '/api/aluno/me/instrumentos',
        summary: 'Instrumento atual e histórico de empréstimos do aluno',
        security: [['sanctum' => []]],
        tags: ['Instrumentos'],
        parameters: [
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Empréstimos atuais e histórico', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'atuais', type: 'array', items: new OA\Items(ref: '#/components/schemas/InstrumentoDoAlunoResource')),
                    new OA\Property(property: 'historico', type: 'array', items: new OA\Items(ref: '#/components/schemas/HistoricoInstrumentoDoAlunoResource')),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, sem vínculo, outra instituição ou módulo de instrumentos indisponível'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function index(ListInstrumentosDoAlunoRequest $request, ListInstrumentosDoAlunoQuery $query)
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;
        $aluno = $this->alunos->resolver($request->user(), $idAluno);
        Gate::authorize('viewAnyDoAluno', [Instrumento::class, $aluno->id]);

        $listas = $query->build($aluno->id);

        return response()->json([
            'data' => [
                'atuais' => InstrumentoDoAlunoResource::collection($listas['atuais'])->resolve(),
                'historico' => HistoricoInstrumentoDoAlunoResource::collection($listas['historico'])->resolve(),
            ],
        ]);
    }
}
