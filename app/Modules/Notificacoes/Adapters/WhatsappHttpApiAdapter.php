<?php

namespace App\Modules\Notificacoes\Adapters;

use App\Modules\Notificacoes\Contracts\WhatsappGatewayInterface;
use App\Modules\Notificacoes\Exceptions\WhatsappGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WhatsappHttpApiAdapter implements WhatsappGatewayInterface
{
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    public function status(string $instance): string
    {
        $response = $this->send(fn () => $this->http(5)->get($this->url("/api/instance/status/{$instance}")));

        if (! $response->successful()) {
            throw new WhatsappGatewayException('Falha ao consultar status da instância.', $response->json());
        }

        $state = $response->json('instance.state') ?? $response->json('state') ?? 'close';

        return match ($state) {
            'open' => self::STATUS_CONNECTED,
            'connecting' => self::STATUS_PENDING,
            default => self::STATUS_DISCONNECTED,
        };
    }

    public function connect(string $instance): ?string
    {
        $response = $this->send(fn () => $this->http(15)->post($this->url('/api/instance/connect'), [
            'instance_name' => $instance,
        ]));

        if ($response->successful()) {
            return $this->extrairQrCode($response);
        }

        $errorData = $response->json();
        $errorMessage = is_array($errorData)
            ? ($errorData['message'] ?? ($errorData['error'] ?? ($errorData['response']['message'][0] ?? '')))
            : $response->body();

        if ($this->instanciaJaExiste((string) $errorMessage)) {
            $reconnect = $this->send(fn () => $this->http(15)->put($this->url("/api/instance/reconnect/{$instance}")));

            if ($reconnect->successful()) {
                return $this->extrairQrCode($reconnect);
            }
        }

        throw new WhatsappGatewayException((string) $errorMessage, $errorData);
    }

    public function reconnect(string $instance): ?string
    {
        $response = $this->send(fn () => $this->http(15)->put($this->url("/api/instance/reconnect/{$instance}")));

        if (! $response->successful()) {
            throw new WhatsappGatewayException('Falha ao reconectar a instância.', $response->json());
        }

        return $this->extrairQrCode($response);
    }

    public function disconnect(string $instance): void
    {
        $response = $this->send(fn () => $this->http(10)->delete($this->url("/api/instance/disconnect/{$instance}")));

        if (! $response->successful()) {
            throw new WhatsappGatewayException('Falha ao desconectar a instância.', $response->json());
        }
    }

    public function sendText(string $instance, string $numero, string $texto): void
    {
        $response = $this->send(fn () => $this->http(15)->post($this->url('/api/message/text'), [
            'instance_name' => $instance,
            'numbers' => [$numero],
            'text' => $texto,
        ]));

        if (! $response->successful()) {
            throw new WhatsappGatewayException($response->body(), $response->json());
        }
    }

    private function http(int $timeout): PendingRequest
    {
        return Http::timeout($timeout);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new WhatsappGatewayException('API do WhatsApp indisponível: '.$e->getMessage(), retryable: true, previous: $e);
        }
    }

    private function instanciaJaExiste(string $mensagem): bool
    {
        return str_contains($mensagem, 'already in use') || str_contains($mensagem, 'Failed to create instance');
    }

    private function extrairQrCode(Response $response): ?string
    {
        return $response->json('qrcode.base64') ?? $response->json('base64');
    }
}
