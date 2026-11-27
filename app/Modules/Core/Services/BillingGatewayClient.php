<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Exceptions\BillingGatewayException;
use App\Modules\Core\Support\PlatformSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP do Centralizador de Pagamentos (API pública /api/v1, autenticada por X-API-Key).
 *
 * URL, API Key e flag billing_enabled vêm de PlatformSettings (painel Platform, com fallback no .env).
 */
class BillingGatewayClient
{
    public function __construct(
        private readonly PlatformSettings $settings,
    ) {}

    public function isConfigured(): bool
    {
        return $this->settings->billingGatewayUrl() !== null
            && $this->settings->billingGatewayApiKey() !== null;
    }

    /**
     * Billing online utilizável: ativo e com URL + API Key.
     */
    public function isAvailable(): bool
    {
        return $this->settings->billingEnabled() && $this->isConfigured();
    }

    /**
     * Verifica a API Key (GET /api/v1/ping). Aceita URL/key ainda não salvas.
     *
     * @return array<string, mixed>
     */
    public function ping(?string $url = null, ?string $apiKey = null): array
    {
        return $this->request('get', '/api/v1/ping', [], $url, $apiKey, exigirAtivo: false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSubscription(array $payload): array
    {
        return $this->request('post', '/api/v1/subscriptions', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscription(string $uuid, bool $sync = false): array
    {
        return $this->request('get', '/api/v1/subscriptions/'.$uuid, $sync ? ['sync' => 1] : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelSubscription(string $uuid): array
    {
        return $this->request('post', '/api/v1/subscriptions/'.$uuid.'/cancel');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload): array
    {
        return $this->request('post', '/api/v1/payments', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $uuid, bool $sync = false): array
    {
        return $this->request('get', '/api/v1/payments/'.$uuid, $sync ? ['sync' => 1] : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $uuid): array
    {
        return $this->request('post', '/api/v1/payments/'.$uuid.'/cancel');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSubaccount(array $payload): array
    {
        return $this->request('post', '/api/v1/subaccounts', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubaccount(string $uuid): array
    {
        return $this->request('get', '/api/v1/subaccounts/'.$uuid);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updatePayoutDestination(string $uuid, array $payload): array
    {
        return $this->request('put', '/api/v1/subaccounts/'.$uuid.'/payout-destination', $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        array $data = [],
        ?string $url = null,
        ?string $apiKey = null,
        bool $exigirAtivo = true,
    ): array {
        $url = filled($url) ? rtrim($url, '/') : $this->settings->billingGatewayUrl();
        $apiKey = filled($apiKey) ? $apiKey : $this->settings->billingGatewayApiKey();

        if ($url === null || $apiKey === null) {
            throw BillingGatewayException::notConfigured();
        }

        if ($exigirAtivo && ! $this->settings->billingEnabled()) {
            throw BillingGatewayException::disabled();
        }

        try {
            $response = $this->http($url, $apiKey)->{$method}($path, $data);
        } catch (ConnectionException $e) {
            throw BillingGatewayException::unreachable($e->getMessage());
        }

        return $this->parse($response);
    }

    private function http(string $url, string $apiKey): PendingRequest
    {
        return Http::withHeaders(['X-API-Key' => $apiKey])
            ->baseUrl($url)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.billing.timeout', 15));
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if ($response->failed()) {
            $errors = isset($body['errors']) && is_array($body['errors']) ? $body['errors'] : null;
            $mensagem = $body['message'] ?? null;

            if ($errors !== null && $response->status() === 422) {
                $mensagem = collect($errors)->flatten()->first() ?? $mensagem;
            }

            throw BillingGatewayException::fromResponse($response->status(), is_string($mensagem) ? $mensagem : null, $errors);
        }

        $data = $body['data'] ?? $body;

        return is_array($data) ? $data : [];
    }
}
