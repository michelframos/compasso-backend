<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Support\PlatformTrialResolver;
use OpenApi\Attributes as OA;

class PlatformConfigController extends Controller
{
    public function __construct(
        private readonly PlatformTrialResolver $trialResolver,
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {}

    #[OA\Get(
        path: '/api/platform/config',
        summary: 'Configurações da plataforma',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(ref: '#/components/schemas/PlatformConfigResponse')
            ),
            new OA\Response(response: 403, description: 'Acesso negado (não é super-admin)'),
        ]
    )]
    public function show()
    {
        $catalog = collect($this->entitlements->catalog())
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'descricao' => $meta['descricao'] ?? '',
            ])
            ->values()
            ->all();

        return response()->json([
            'default_trial_days' => $this->trialResolver->defaultTrialDays(),
            'modulos_app' => $catalog,
        ]);
    }
}
