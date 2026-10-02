<?php

namespace App\Modules\Notificacoes\Contracts;

use App\Modules\Notificacoes\Exceptions\WhatsappGatewayException;

interface WhatsappGatewayInterface
{
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_PENDING = 'pending';
    public const STATUS_DISCONNECTED = 'disconnected';

    /**
     * @return self::STATUS_*
     *
     * @throws WhatsappGatewayException
     */
    public function status(string $instance): string;

    /**
     * Cria (ou reaproveita, se já existir) a instância e retorna o QR Code em base64.
     *
     * @throws WhatsappGatewayException
     */
    public function connect(string $instance): ?string;

    /**
     * @throws WhatsappGatewayException
     */
    public function reconnect(string $instance): ?string;

    /**
     * @throws WhatsappGatewayException
     */
    public function disconnect(string $instance): void;

    /**
     * @throws WhatsappGatewayException
     */
    public function sendText(string $instance, string $numero, string $texto): void;
}
