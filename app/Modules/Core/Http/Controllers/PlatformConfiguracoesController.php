<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Exceptions\BillingGatewayException;
use App\Modules\Core\Http\Requests\Platform\UpdatePlatformConfiguracoesRequest;
use App\Modules\Core\Services\BillingGatewayClient;
use App\Modules\Core\Support\EmailTemplateRenderer;
use App\Modules\Core\Support\PlatformSettings;
use App\Modules\Core\Support\PlatformTrialResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PlatformConfiguracoesController extends Controller
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly PlatformTrialResolver $trialResolver,
        private readonly BillingGatewayClient $billingGateway,
    ) {}

    #[OA\Get(
        path: '/api/platform/configuracoes',
        summary: 'Configurações editáveis da plataforma (gratuidade, e-mail de cadastro e billing online)',
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

        $billing = [];

        if (array_key_exists('billing_gateway_url', $data)) {
            $url = $data['billing_gateway_url'];
            $billing[PlatformSettings::BILLING_GATEWAY_URL] = filled($url) ? rtrim($url, '/') : null;
        }

        if (array_key_exists('billing_enabled', $data)) {
            $billing[PlatformSettings::BILLING_ENABLED] = $data['billing_enabled'] ? '1' : '0';
        }

        // A API Key só é atualizada quando vier preenchida e diferente do valor mascarado devolvido pelo GET.
        $novaKey = $data['billing_gateway_api_key'] ?? null;

        if (filled($novaKey) && $novaKey !== PlatformSettings::maskApiKey($this->settings->billingGatewayApiKey())) {
            $billing[PlatformSettings::BILLING_GATEWAY_API_KEY] = trim($novaKey);
        }

        unset($data['billing_gateway_url'], $data['billing_enabled'], $data['billing_gateway_api_key']);

        $this->settings->setMany([...$data, ...$billing]);

        return response()->json($this->payload());
    }

    #[OA\Post(
        path: '/api/platform/configuracoes/billing/testar',
        summary: 'Testar conexão com o centralizador de pagamentos (GET /api/v1/ping)',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        responses: [
            new OA\Response(response: 200, description: 'Conexão OK'),
            new OA\Response(response: 403, description: 'Acesso negado (não é super-admin)'),
            new OA\Response(response: 422, description: 'Sem configuração ou API Key inválida'),
            new OA\Response(response: 502, description: 'Centralizador indisponível'),
        ]
    )]
    public function testBilling(Request $request): JsonResponse
    {
        $data = $request->validate([
            'billing_gateway_url' => ['nullable', 'url', 'max:255'],
            'billing_gateway_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $key = $data['billing_gateway_api_key'] ?? null;

        // Valor mascarado (ou vazio) significa "usar a key já salva".
        if (! filled($key) || $key === PlatformSettings::maskApiKey($this->settings->billingGatewayApiKey())) {
            $key = null;
        }

        try {
            $resposta = $this->billingGateway->ping($data['billing_gateway_url'] ?? null, $key);
        } catch (BillingGatewayException $e) {
            $status = in_array($e->httpStatus(), [401, 403, 422], true) ? 422 : 502;

            return response()->json(['ok' => false, 'message' => $e->getMessage()], $status);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Conexão com o centralizador estabelecida.',
            'system' => $resposta['system']['name'] ?? null,
        ]);
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
            'billing_gateway_url' => $this->settings->billingGatewayUrl(),
            'billing_enabled' => $this->settings->billingEnabled(),
            'billing_gateway_api_key_masked' => PlatformSettings::maskApiKey($this->settings->billingGatewayApiKey()),
            'billing_configured' => $this->billingGateway->isConfigured(),
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
