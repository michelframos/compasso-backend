<?php

namespace App\Modules\Espetaculos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Espetaculos\Http\Requests\Ensaio\ListEnsaiosRequest;
use App\Modules\Espetaculos\Http\Requests\Ensaio\StoreEnsaioRequest;
use App\Modules\Espetaculos\Http\Requests\Ensaio\UpdateEnsaioRequest;
use App\Modules\Espetaculos\Http\Resources\EnsaioResource;
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\Ensaio;
use App\Modules\Espetaculos\UseCases\Ensaio\AgendarEnsaioUseCase;
use App\Modules\Espetaculos\UseCases\Ensaio\AtualizarEnsaioUseCase;
use App\Modules\Espetaculos\UseCases\Ensaio\ExcluirEnsaioUseCase;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class EnsaioController extends Controller
{
    #[OA\Get(
        path: '/api/ensaios',
        summary: 'Agenda de ensaios (professor: os que conduz e os das apresentações das suas turmas ou com alunos seus)',
        security: [['sanctum' => []]],
        tags: ['Espetaculos'],
        parameters: [
            new OA\Parameter(name: 'id_apresentacao', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'data_inicio', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'data_fim', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ensaios em ordem cronológica', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EnsaioResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso ou módulo de espetáculos indisponível'),
            new OA\Response(response: 422, description: 'Filtros inválidos'),
        ]
    )]
    public function index(ListEnsaiosRequest $request)
    {
        $filtros = $request->validated();

        $ensaios = Ensaio::query()
            ->visivelPara($request->user())
            ->when($filtros['id_apresentacao'] ?? null, fn ($q, $id) => $q->where('id_apresentacao', $id))
            ->when($filtros['data_inicio'] ?? null, fn ($q, $data) => $q->where('data', '>=', $data))
            ->when($filtros['data_fim'] ?? null, fn ($q, $data) => $q->where('data', '<=', $data))
            ->with(Ensaio::DETALHES)
            ->orderBy('data')
            ->orderBy('hora_inicio')
            ->get();

        return EnsaioResource::collection($ensaios);
    }

    #[OA\Post(
        path: '/api/ensaios',
        summary: 'Agendar ensaio de uma apresentação',
        security: [['sanctum' => []]],
        tags: ['Espetaculos'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreEnsaioRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Ensaio agendado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/EnsaioResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apresentação sem turma ou alunos do professor'),
            new OA\Response(response: 422, description: 'Erro de validação, data após o espetáculo ou espetáculo encerrado'),
        ]
    )]
    public function store(StoreEnsaioRequest $request, AgendarEnsaioUseCase $agendar)
    {
        $dados = $request->validated();
        $apresentacao = Apresentacao::with('espetaculo')->findOrFail($dados['id_apresentacao']);

        Gate::authorize('gerenciarEnsaios', $apresentacao);

        unset($dados['id_apresentacao']);
        $ensaio = $agendar->execute($apresentacao, $dados, $request->user());

        return new EnsaioResource($ensaio->load(Ensaio::DETALHES));
    }

    #[OA\Put(
        path: '/api/ensaios/{ensaio}',
        summary: 'Editar ensaio (professor: só os que conduz)',
        security: [['sanctum' => []]],
        tags: ['Espetaculos'],
        parameters: [
            new OA\Parameter(name: 'ensaio', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateEnsaioRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Ensaio atualizado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/EnsaioResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Ensaio conduzido por outro professor'),
            new OA\Response(response: 404, description: 'Ensaio não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(UpdateEnsaioRequest $request, Ensaio $ensaio, AtualizarEnsaioUseCase $atualizar)
    {
        Gate::authorize('update', $ensaio);

        $ensaio = $atualizar->execute($ensaio->load('apresentacao.espetaculo'), $request->validated(), $request->user());

        return new EnsaioResource($ensaio->load(Ensaio::DETALHES));
    }

    #[OA\Delete(
        path: '/api/ensaios/{ensaio}',
        summary: 'Excluir ensaio (professor: só os que conduz)',
        security: [['sanctum' => []]],
        tags: ['Espetaculos'],
        parameters: [
            new OA\Parameter(name: 'ensaio', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Ensaio excluído'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Ensaio conduzido por outro professor'),
            new OA\Response(response: 404, description: 'Ensaio não encontrado'),
        ]
    )]
    public function destroy(Ensaio $ensaio, ExcluirEnsaioUseCase $excluir)
    {
        Gate::authorize('delete', $ensaio);

        $excluir->execute($ensaio);

        return response()->noContent();
    }
}
