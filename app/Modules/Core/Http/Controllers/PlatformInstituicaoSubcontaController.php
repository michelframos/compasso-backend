<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Exceptions\BillingGatewayException;
use App\Modules\Core\Http\Concerns\RespondsBillingGatewayErrors;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\UseCases\Assinatura\ProvisionarSubcontaEscolaUseCase;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PlatformInstituicaoSubcontaController extends Controller
{
    use RespondsBillingGatewayErrors;

    public function __construct(
        private readonly ProvisionarSubcontaEscolaUseCase $provisionarSubconta,
    ) {}

    #[OA\Post(
        path: '/api/platform/instituicoes/{id}/subconta',
        summary: 'Cria ou retenta a subconta da escola (suporte Platform)',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        responses: [
            new OA\Response(response: 200, description: 'Subconta solicitada'),
            new OA\Response(response: 422, description: 'Dados incompletos / billing indisponível'),
            new OA\Response(response: 502, description: 'Centralizador indisponível'),
        ]
    )]
    public function store(Instituicao $instituicao): JsonResponse
    {
        try {
            $instituicao = $this->provisionarSubconta->execute($instituicao);
        } catch (BillingGatewayException $e) {
            return $this->billingGatewayError($e);
        }

        return response()->json(['data' => [
            'id_instituicao' => $instituicao->id,
            'status' => $instituicao->gateway_subaccount_status,
            'ativa' => $instituicao->subcontaAtiva(),
            'last_error' => $instituicao->gateway_subaccount_last_error,
        ]]);
    }
}
