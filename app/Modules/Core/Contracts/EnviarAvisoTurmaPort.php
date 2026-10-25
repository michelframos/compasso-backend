<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Core\Domain\Notificacoes\Notificacao;

interface EnviarAvisoTurmaPort
{
    public const EMAIL = 'email';
    public const WHATSAPP = 'whatsapp';
    public const CANAIS = [self::EMAIL, self::WHATSAPP];

    /** @return array<string, bool> canal => a escola consegue usar agora */
    public function canaisDisponiveis(): array;

    /**
     * Enfileira as mensagens; o resultado de cada uma chega pelo evento NotificacaoProcessada.
     *
     * @param  list<array{canal: string, notificacao: Notificacao}>  $mensagens
     */
    public function enviar(array $mensagens): void;
}
