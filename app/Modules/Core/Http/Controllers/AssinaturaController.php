<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Assinatura\SolicitarAssinaturaRequest;
use App\Modules\Core\Http\Resources\PublicPlanoResource;
use App\Modules\Core\Http\Resources\SolicitacaoAssinaturaResource;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Models\SolicitacaoAssinatura;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\UseCases\Assinatura\SolicitarAssinaturaUseCase;
use OpenApi\Attributes as OA;

class AssinaturaController extends Controller
{
    public function __construct(
        private readonly SolicitarAssinaturaUseCase $solicitarAssinatura,
    ) {}

    #[OA\Get(
        path: '/api/assinatura/planos',
        summary: 'Lista planos ativos para solicitação',
        security: [['sanctum' => []]],
        tags: ['Assinatura'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function planos()
    {
        return PublicPlanoResource::collection(PlanoAssinatura::ativosParaVitrine());
    }

    #[OA\Get(
        path: '/api/assinatura/solicitacao',
        summary: 'Retorna a solicitação pendente da instituição atual',
        security: [['sanctum' => []]],
        tags: ['Assinatura'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function solicitacaoAtual()
    {
        $instituicaoId = InstituicaoContext::id();

        $solicitacao = SolicitacaoAssinatura::query()
            ->with('plano')
            ->where('id_instituicao', $instituicaoId)
            ->where('status', SolicitacaoAssinatura::STATUS_PENDENTE)
            ->latest()
            ->first();

        if ($solicitacao === null) {
            return response()->json(['data' => null]);
        }

        return new SolicitacaoAssinaturaResource($solicitacao);
    }

    #[OA\Post(
        path: '/api/assinatura/solicitar',
        summary: 'Solicita ativação de um plano',
        security: [['sanctum' => []]],
        tags: ['Assinatura'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SolicitarAssinaturaRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Solicitação criada'),
            new OA\Response(response: 422, description: 'Validação'),
        ]
    )]
    public function solicitar(SolicitarAssinaturaRequest $request)
    {
        $solicitacao = $this->solicitarAssinatura->execute(
            $request->user(),
            $request->validated(),
        );

        return (new SolicitacaoAssinaturaResource($solicitacao))
            ->response()
            ->setStatusCode(201);
    }
}
