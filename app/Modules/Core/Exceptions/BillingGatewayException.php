<?php

namespace App\Modules\Core\Exceptions;

use RuntimeException;

/**
 * Falha na comunicação ou no processamento pelo Centralizador de Pagamentos.
 */
class BillingGatewayException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $errors
     */
    public function __construct(
        string $message,
        private readonly int $httpStatus = 502,
        private readonly ?array $errors = null,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public static function notConfigured(): self
    {
        return new self('Billing online não configurado: informe a URL e a API Key do centralizador.', 422);
    }

    public static function disabled(): self
    {
        return new self('Billing online está desativado.', 422);
    }

    public static function unreachable(string $detalhe): self
    {
        return new self('Não foi possível conectar ao centralizador de pagamentos: '.$detalhe, 502);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    public static function fromResponse(int $status, ?string $mensagem, ?array $errors = null): self
    {
        $mensagem = filled($mensagem) ? $mensagem : 'Erro no centralizador de pagamentos.';

        if ($status === 401 || $status === 403) {
            return new self('API Key do centralizador inválida ou sem permissão.', $status, $errors);
        }

        return new self($mensagem, $status, $errors);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function errors(): ?array
    {
        return $this->errors;
    }
}
