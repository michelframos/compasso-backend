<?php

namespace App\Modules\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Resultado final (ou de uma tentativa com erro) de uma notificação enfileirada. */
final class NotificacaoProcessada
{
    use Dispatchable;

    public const ENVIADO = 'enviado';
    public const ERRO = 'erro';

    public function __construct(
        public readonly string $referenciaType,
        public readonly int $referenciaId,
        public readonly string $status,
        public readonly ?string $erro = null,
    ) {}
}
