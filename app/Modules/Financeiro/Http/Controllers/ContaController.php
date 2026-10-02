<?php

namespace App\Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use App\Modules\Financeiro\Http\Requests\Conta\StoreContaRequest;
use App\Modules\Financeiro\Http\Requests\Conta\UpdateContaRequest;
use App\Modules\Financeiro\Http\Resources\ContaResource;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Queries\ContaQuery;
use App\Modules\Financeiro\UseCases\Conta\CreateContaUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Financeiro', description: 'Gerenciamento de contas (receitas e despesas)')]
class ContaController extends Controller
{
    use ResolvesSorting;

    public function __construct(
        private readonly ContaQuery $contaQuery,
        private readonly CreateContaUseCase $createConta,
    ) {}

    #[OA\Get(
        path: '/api/contas',
        summary: 'Listar contas',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(name: 'situacao', description: 'Filtro por situação: a_vencer, vencendo_hoje, atrasadas, pagas', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['a_vencer', 'vencendo_hoje', 'atrasadas', 'pagas']))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de contas',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ContaResource')
                        ),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object')
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $query = Conta::with(['categoria', 'aluno.usuario', 'professor.usuario'])->visivelPara($request->user());

        $this->contaQuery->aplicar($query, $request->query());
        $this->applySorting($query, ['id', 'descricao', 'valor', 'data_vencimento', 'status', 'tipo'], 'data_vencimento');

        $contas = $query->paginate(15);
        return ContaResource::collection($contas);
    }

    #[OA\Post(
        path: '/api/contas',
        summary: 'Criar conta',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreContaRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Conta criada com sucesso',
                content: new OA\JsonContent(ref: '#/components/schemas/ContaResource')
            ),
            new OA\Response(
                response: 422,
                description: 'Erro de validação'
            )
        ]
    )]
    public function store(StoreContaRequest $request)
    {
        $contas = $this->createConta->execute($request->validated());

        if ($contas->count() > 1) {
            return ContaResource::collection($contas);
        }

        return new ContaResource($contas->first()->load(['categoria', 'aluno.usuario', 'professor.usuario']));
    }

    #[OA\Get(
        path: '/api/contas/{conta}',
        summary: 'Detalhes da conta',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(
                name: 'conta',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalhes da conta',
                content: new OA\JsonContent(ref: '#/components/schemas/ContaResource')
            ),
            new OA\Response(
                response: 404,
                description: 'Conta não encontrada'
            )
        ]
    )]
    public function show(Conta $conta)
    {
        Gate::authorize('view', $conta);

        return new ContaResource($conta->load(['categoria', 'aluno.usuario', 'professor.usuario']));
    }

    #[OA\Put(
        path: '/api/contas/{conta}',
        summary: 'Atualizar conta',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(
                name: 'conta',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateContaRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Conta atualizada com sucesso',
                content: new OA\JsonContent(ref: '#/components/schemas/ContaResource')
            ),
            new OA\Response(
                response: 404,
                description: 'Conta não encontrada'
            ),
            new OA\Response(
                response: 422,
                description: 'Erro de validação'
            )
        ]
    )]
    public function update(UpdateContaRequest $request, Conta $conta)
    {
        $conta->update($request->validated());
        return new ContaResource($conta->load(['categoria', 'aluno.usuario', 'professor.usuario']));
    }

    #[OA\Delete(
        path: '/api/contas/{conta}',
        summary: 'Excluir conta',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(
                name: 'conta',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Conta excluída'
            ),
            new OA\Response(
                response: 404,
                description: 'Conta não encontrada'
            )
        ]
    )]
    public function destroy(Conta $conta)
    {
        $conta->delete();
        return response()->noContent();
    }
}
