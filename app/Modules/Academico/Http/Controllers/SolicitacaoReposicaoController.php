<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Reposicao\DecidirReposicaoRequest;
use App\Modules\Academico\Http\Requests\Reposicao\ListReposicoesRequest;
use App\Modules\Academico\Http\Resources\SolicitacaoReposicaoResource;
use App\Modules\Academico\Models\SolicitacaoReposicao;
use App\Modules\Academico\Queries\OpcoesReposicaoQuery;
use App\Modules\Academico\Http\Resources\OpcaoReposicaoResource;
use App\Modules\Academico\UseCases\Reposicao\DecidirReposicaoUseCase;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class SolicitacaoReposicaoController extends Controller
{
    #[OA\Get(
        path: '/api/solicitacoes-reposicoes',
        summary: 'Fila de pedidos de reposição',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: SolicitacaoReposicao::STATUS)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedidos visíveis para o usuário. O professor só vê as turmas dele.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SolicitacaoReposicaoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido'),
        ]
    )]
    public function index(ListReposicoesRequest $request)
    {
        $status = $request->validated('status') ?? SolicitacaoReposicao::STATUS_PENDENTE;

        $pedidos = SolicitacaoReposicao::query()
            ->visivelPara($request->user())
            ->with(['aluno.usuario', 'perdida.turma', 'destino.turma'])
            ->where('status', $status)
            ->orderBy('id')
            ->get();

        return SolicitacaoReposicaoResource::collection($pedidos);
    }

    #[OA\Get(
        path: '/api/solicitacoes-reposicoes/{solicitacaoReposicao}/opcoes',
        summary: 'Aulas em que o aluno pode repor esta falta',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'solicitacaoReposicao', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Aulas com vaga e sem conflito', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/OpcaoReposicaoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Professor de outra turma'),
        ]
    )]
    public function opcoes(SolicitacaoReposicao $solicitacaoReposicao, OpcoesReposicaoQuery $opcoes)
    {
        $solicitacaoReposicao->load('perdida.turma');
        Gate::authorize('decidir', $solicitacaoReposicao);

        return OpcaoReposicaoResource::collection(
            $opcoes->listar($solicitacaoReposicao->id_aluno, $solicitacaoReposicao->perdida)
        );
    }

    #[OA\Post(
        path: '/api/solicitacoes-reposicoes/{solicitacaoReposicao}/decisao',
        summary: 'Aprovar ou recusar uma reposição. Ao aprovar, o aluno entra na chamada da aula de destino.',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'solicitacaoReposicao', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DecidirReposicaoRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Decisão registrada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/SolicitacaoReposicaoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Professor de outra turma ou papel indevido'),
            new OA\Response(response: 404, description: 'Pedido não encontrado'),
            new OA\Response(response: 422, description: 'Pedido já decidido, sem vaga ou com conflito de horário'),
        ]
    )]
    public function decidir(
        DecidirReposicaoRequest $request,
        SolicitacaoReposicao $solicitacaoReposicao,
        DecidirReposicaoUseCase $decidir,
    ) {
        $solicitacaoReposicao->load(['perdida.turma', 'destino.turma']);
        Gate::authorize('decidir', $solicitacaoReposicao);

        return new SolicitacaoReposicaoResource(
            $decidir->execute($solicitacaoReposicao, $request->validated(), $request->user())
        );
    }
}
