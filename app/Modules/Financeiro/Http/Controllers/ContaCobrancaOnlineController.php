<?php

namespace App\Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Exceptions\BillingGatewayException;
use App\Modules\Core\Http\Concerns\RespondsBillingGatewayErrors;
use App\Modules\Financeiro\Http\Requests\CobrancaOnline\CriarCobrancaOnlineContaRequest;
use App\Modules\Financeiro\Http\Resources\ContaCobrancaGatewayResource;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\UseCases\CobrancaOnline\CancelarCobrancaOnlineContaUseCase;
use App\Modules\Financeiro\UseCases\CobrancaOnline\CriarCobrancaOnlineContaUseCase;
use App\Modules\Financeiro\UseCases\CobrancaOnline\ObterCobrancaOnlineContaUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Financeiro', description: 'Cobrança online de contas a receber (Centralizador de Pagamentos)')]
class ContaCobrancaOnlineController extends Controller
{
    use RespondsBillingGatewayErrors;

    public function __construct(
        private readonly CriarCobrancaOnlineContaUseCase $criarCobranca,
        private readonly ObterCobrancaOnlineContaUseCase $obterCobranca,
        private readonly CancelarCobrancaOnlineContaUseCase $cancelarCobranca,
    ) {}

    #[OA\Post(
        path: '/api/contas/{conta}/cobranca-online',
        summary: 'Gerar (ou reaproveitar) a cobrança online da conta',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [new OA\Parameter(name: 'conta', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['method'], properties: [
            new OA\Property(property: 'method', type: 'string', enum: ['pix', 'credit_card', 'boleto']),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Cobrança criada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ContaCobrancaGatewayResource'),
            ])),
            new OA\Response(response: 200, description: 'Cobrança em aberto reaproveitada'),
            new OA\Response(response: 422, description: 'Billing indisponível, escola sem subconta ativa, conta sem saldo ou meio inválido'),
            new OA\Response(response: 502, description: 'Centralizador indisponível'),
        ]
    )]
    public function store(CriarCobrancaOnlineContaRequest $request, Conta $conta): JsonResponse
    {
        Gate::authorize('view', $conta);

        try {
            $cobranca = $this->criarCobranca->execute($conta, $request->validated('method'));
        } catch (BillingGatewayException $e) {
            return $this->billingGatewayError($e);
        }

        return (new ContaCobrancaGatewayResource($cobranca))
            ->response()
            ->setStatusCode($cobranca->wasRecentlyCreated ? 201 : 200);
    }

    #[OA\Get(
        path: '/api/contas/{conta}/cobranca-online',
        summary: 'Cobrança online da conta (opcionalmente sincronizada com o gateway)',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [
            new OA\Parameter(name: 'conta', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sync', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cobrança atual ou data: null', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ContaCobrancaGatewayResource', nullable: true),
            ])),
            new OA\Response(response: 403, description: 'Sem acesso à conta'),
            new OA\Response(response: 404, description: 'Conta não encontrada'),
        ]
    )]
    public function show(Request $request, Conta $conta): JsonResponse
    {
        Gate::authorize('view', $conta);

        $cobranca = $this->obterCobranca->execute($conta, $request->boolean('sync'));

        return response()->json([
            'data' => $cobranca !== null ? (new ContaCobrancaGatewayResource($cobranca))->resolve($request) : null,
        ]);
    }

    #[OA\Post(
        path: '/api/contas/{conta}/cobranca-online/cancelar',
        summary: 'Cancelar a cobrança online em aberto da conta',
        security: [['sanctum' => []]],
        tags: ['Financeiro'],
        parameters: [new OA\Parameter(name: 'conta', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Cobrança cancelada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ContaCobrancaGatewayResource'),
            ])),
            new OA\Response(response: 422, description: 'Sem cobrança em aberto, já paga ou billing indisponível'),
            new OA\Response(response: 502, description: 'Centralizador indisponível'),
        ]
    )]
    public function cancelar(Conta $conta): JsonResponse|ContaCobrancaGatewayResource
    {
        Gate::authorize('view', $conta);

        try {
            $cobranca = $this->cancelarCobranca->execute($conta);
        } catch (BillingGatewayException $e) {
            return $this->billingGatewayError($e);
        }

        return new ContaCobrancaGatewayResource($cobranca);
    }
}
