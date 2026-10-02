<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Platform\UpdatePlatformConfiguracoesRequest;
use App\Modules\Core\Support\EmailTemplateRenderer;
use App\Modules\Core\Support\PlatformSettings;
use App\Modules\Core\Support\PlatformTrialResolver;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PlatformConfiguracoesController extends Controller
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly PlatformTrialResolver $trialResolver,
    ) {}

    #[OA\Get(
        path: '/api/platform/configuracoes',
        summary: 'Configurações editáveis da plataforma (gratuidade e e-mail de cadastro)',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 403, description: 'Acesso negado (não é super-admin)'),
        ]
    )]
    public function show(): JsonResponse
    {
        return response()->json($this->payload());
    }

    #[OA\Put(
        path: '/api/platform/configuracoes',
        summary: 'Atualizar configurações da plataforma',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 403, description: 'Acesso negado (não é super-admin)'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(UpdatePlatformConfiguracoesRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('default_trial_days', $data)) {
            $data['default_trial_days'] = (string) $data['default_trial_days'];
        }

        $this->settings->setMany($data);

        return response()->json($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'default_trial_days' => $this->trialResolver->defaultTrialDays(),
            'email_cadastro_assunto' => $this->settings->emailCadastroAssunto(),
            'email_cadastro_corpo' => $this->settings->emailCadastroCorpo(),
            'variaveis_email' => collect(EmailTemplateRenderer::VARIAVEIS_CADASTRO)
                ->map(fn (string $descricao, string $chave) => [
                    'chave' => $chave,
                    'descricao' => $descricao,
                ])
                ->values()
                ->all(),
        ];
    }
}
