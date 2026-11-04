<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\JustificativaFalta\DecidirJustificativaRequest;
use App\Modules\Academico\Http\Requests\JustificativaFalta\ListJustificativasRequest;
use App\Modules\Academico\Http\Resources\JustificativaFaltaResource;
use App\Modules\Academico\Models\JustificativaFalta;
use App\Modules\Academico\UseCases\JustificativaFalta\DecidirJustificativaUseCase;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class JustificativaFaltaController extends Controller
{
    #[OA\Get(
        path: '/api/justificativas-faltas',
        summary: 'Fila de avisos de ausência e justificativas',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: JustificativaFalta::STATUS)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedidos visíveis para o usuário. O professor só vê as turmas dele.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/JustificativaFaltaResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido'),
        ]
    )]
    public function index(ListJustificativasRequest $request)
    {
        $status = $request->validated('status') ?? JustificativaFalta::STATUS_PENDENTE;

        $pedidos = JustificativaFalta::query()
            ->visivelPara($request->user())
            ->with(['aluno.usuario', 'aula.turma'])
            ->where('status', $status)
            ->orderBy('id')
            ->get();

        return JustificativaFaltaResource::collection($pedidos);
    }

    #[OA\Post(
        path: '/api/justificativas-faltas/{justificativaFalta}/decisao',
        summary: 'Aceitar ou recusar uma justificativa. Ao aceitar, a presença fica justificada.',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'justificativaFalta', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DecidirJustificativaRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Decisão registrada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/JustificativaFaltaResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Professor de outra turma ou papel indevido'),
            new OA\Response(response: 404, description: 'Justificativa não encontrada'),
            new OA\Response(response: 422, description: 'Pedido já decidido'),
        ]
    )]
    public function decidir(
        DecidirJustificativaRequest $request,
        JustificativaFalta $justificativaFalta,
        DecidirJustificativaUseCase $decidir,
    ) {
        $justificativaFalta->load('aula.turma');
        Gate::authorize('decidir', $justificativaFalta);

        return new JustificativaFaltaResource(
            $decidir->execute($justificativaFalta, $request->validated(), $request->user())
        );
    }
}
