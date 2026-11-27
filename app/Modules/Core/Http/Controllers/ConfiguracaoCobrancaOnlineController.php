<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Assinatura\UpdateConfiguracaoCobrancaOnlineRequest;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\UseCases\Assinatura\GetConfiguracaoCobrancaOnlineUseCase;
use App\Modules\Core\UseCases\Assinatura\UpdateConfiguracaoCobrancaOnlineUseCase;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Core', description: 'Meios de cobrança online da escola')]
class ConfiguracaoCobrancaOnlineController extends Controller
{
    public function __construct(
        private readonly GetConfiguracaoCobrancaOnlineUseCase $get,
        private readonly UpdateConfiguracaoCobrancaOnlineUseCase $update,
    ) {}

    #[OA\Get(
        path: '/api/configuracao-cobranca-online',
        summary: 'Meios de cobrança online habilitados e status da subconta',
        security: [['sanctum' => []]],
        tags: ['Core'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->get->execute($this->instituicao())]);
    }

    #[OA\Put(
        path: '/api/configuracao-cobranca-online',
        summary: 'Atualizar meios de cobrança online (pix, credit_card, boleto; mínimo 1)',
        security: [['sanctum' => []]],
        tags: ['Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateConfiguracaoCobrancaOnlineRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function atualizar(UpdateConfiguracaoCobrancaOnlineRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->update->execute($this->instituicao(), $request->validated()),
        ]);
    }

    private function instituicao(): Instituicao
    {
        return Instituicao::query()->findOrFail(InstituicaoContext::id());
    }
}
