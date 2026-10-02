<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\DepoimentoRepositoryInterface;
use App\Modules\Core\Contracts\SiteModuloRepositoryInterface;
use App\Modules\Core\Http\Requests\Public\PublicSignupRequest;
use App\Modules\Core\Http\Resources\PublicDepoimentoResource;
use App\Modules\Core\Http\Resources\PublicPlanoResource;
use App\Modules\Core\Http\Resources\PublicSiteModuloResource;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Support\PlatformTrialResolver;
use App\Modules\Core\UseCases\Public\PublicSignupUseCase;
use OpenApi\Attributes as OA;

class PublicController extends Controller
{
    public function __construct(
        private readonly PlatformTrialResolver $trialResolver,
        private readonly PublicSignupUseCase $signup,
        private readonly DepoimentoRepositoryInterface $depoimentos,
        private readonly SiteModuloRepositoryInterface $modulos,
    ) {}

    #[OA\Get(
        path: '/api/public/planos',
        summary: 'Lista planos ativos (público)',
        tags: ['Public'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function planos()
    {
        return PublicPlanoResource::collection(PlanoAssinatura::ativosParaVitrine());
    }

    #[OA\Get(
        path: '/api/public/depoimentos',
        summary: 'Lista depoimentos aprovados (público)',
        tags: ['Public'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function depoimentos()
    {
        return PublicDepoimentoResource::collection(
            $this->depoimentos->aprovadosParaVitrine()
        );
    }

    #[OA\Get(
        path: '/api/public/modulos',
        summary: 'Lista módulos aprovados do site (público)',
        tags: ['Public'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function modulos()
    {
        return PublicSiteModuloResource::collection(
            $this->modulos->aprovadosParaVitrine()
        );
    }

    #[OA\Get(
        path: '/api/public/config',
        summary: 'Configurações públicas do site',
        tags: ['Public'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function config()
    {
        return response()->json([
            'default_trial_days' => $this->trialResolver->defaultTrialDays(),
        ]);
    }

    #[OA\Post(
        path: '/api/public/signup',
        summary: 'Cadastro self-service (trial)',
        tags: ['Public'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PublicSignupRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Conta criada'),
            new OA\Response(response: 422, description: 'Validação'),
            new OA\Response(response: 429, description: 'Rate limit'),
        ]
    )]
    public function signup(PublicSignupRequest $request)
    {
        $result = $this->signup->execute($request->validated());

        return response()->json([
            'token' => $result['token'],
            'user' => [
                'id' => $result['user']->id,
                'nome' => $result['user']->nome,
                'email' => $result['user']->email,
            ],
            'tenant' => [
                'id' => $result['tenant']->id,
                'slug' => $result['tenant']->slug,
                'nome_fantasia' => $result['tenant']->nome_fantasia,
            ],
        ], 201);
    }
}
