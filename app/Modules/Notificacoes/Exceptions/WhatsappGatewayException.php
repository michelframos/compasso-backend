<?php

namespace App\Modules\Notificacoes\Exceptions;

use RuntimeException;
use Throwable;

class WhatsappGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly mixed $details = null,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
