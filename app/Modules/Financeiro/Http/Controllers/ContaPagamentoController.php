<?php

namespace App\Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Models\ContaPagamento;
use App\Modules\Financeiro\Http\Requests\ContaPagamento\StoreContaPagamentoRequest;
use App\Modules\Financeiro\Http\Requests\ContaPagamento\StoreContaPagamentoLoteRequest;
use App\Modules\Financeiro\Http\Resources\ContaPagamentoResource;
use App\Modules\Financeiro\UseCases\Pagamento\RegistrarPagamentosEmLoteUseCase;
use App\Modules\Financeiro\UseCases\Pagamento\RegistrarPagamentoUseCase;
use App\Modules\Financeiro\UseCases\Pagamento\RemoverPagamentoUseCase;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Pagamentos de Contas', description: 'Gerenciamento de pagamentos parciais para contas')]
class ContaPagamentoController extends Controller
{
    public function __construct(
        private readonly RegistrarPagamentoUseCase $registrarPagamento,
        private readonly RegistrarPagamentosEmLoteUseCase $registrarPagamentosEmLote,
        private readonly RemoverPagamentoUseCase $removerPagamento,
    ) {}

    #[OA\Get(
        path: '/api/contas/{conta}/pagamentos',
        summary: 'Listar pagamentos de uma conta',
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
                description: 'Lista de pagamentos',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/ContaPagamentoResource')
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Conta não encontrada'
            )
        ]
    )]
    public function index(Conta $conta)
    {
        Gate::authorize('view', $conta);

        return ContaPagamentoResource::collection($conta->pagamentos);
    }

    #[OA\Post(
        path: '/api/contas/{conta}/pagamentos',
        summary: 'Registrar um novo pagamento para a conta',
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
            content: new OA\JsonContent(ref: '#/components/schemas/StoreContaPagamentoRequest')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Pagamento registrado com sucesso',
                content: new OA\JsonContent(ref: '#/components/schemas/ContaPagamentoResource')
            ),
            new OA\Response(
                response: 422,
                description: 'Erro de validação (ex: valor de pagamento maior que saldo devedor)'
            ),
            new OA\Response(
                response: 404,
                description: 'Conta não encontrada'
            )
        ]
    )]
    public function store(StoreContaPagamentoRequest $request, Conta $conta)
    {
        $pagamento = $this->registrarPagamento->execute($conta, $request->validated());

        return new ContaPagamentoResource($pagamento);
    }

    #[OA\Post(
        path: '/api/contas/pagamentos/lote',
        summary: 'Registrar pagamentos em lote',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['conta_ids', 'data_pagamento', 'forma_pagamento'],
                properties: [
                    new OA\Property(
                        property: 'conta_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(property: 'data_pagamento', type: 'string', format: 'date'),
                    new OA\Property(property: 'forma_pagamento', type: 'string'),
                    new OA\Property(property: 'observacoes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pagamentos processados com sucesso'
            )
        ]
    )]
    public function storeEmLote(StoreContaPagamentoLoteRequest $request)
    {
        $podeAcessar = fn (Conta $conta): bool => Gate::allows('pagar', $conta);

        try {
            $contasPagas = $this->registrarPagamentosEmLote->execute($request->validated(), $podeAcessar);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro ao processar pagamentos: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Pagamentos em lote processados com sucesso.',
            'contas_afetadas' => $contasPagas
        ], 200);
    }

    #[OA\Delete(
        path: '/api/pagamentos/{pagamento}',
        summary: 'Deletar um registro de pagamento e reverter o status da conta',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(
                name: 'pagamento',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Pagamento removido com sucesso'
            ),
            new OA\Response(
                response: 404,
                description: 'Pagamento não encontrado'
            )
        ]
    )]
    public function destroy(ContaPagamento $pagamento)
    {
        $this->removerPagamento->execute($pagamento);

        return response()->noContent();
    }
}
