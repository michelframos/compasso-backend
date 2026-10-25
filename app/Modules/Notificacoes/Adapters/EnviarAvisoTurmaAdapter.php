<?php

namespace App\Modules\Notificacoes\Adapters;

use App\Modules\Core\Contracts\EnviarAvisoTurmaPort;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Notificacoes\Contracts\WhatsappGatewayInterface;
use App\Modules\Notificacoes\Jobs\EnviarAvisoTurmaJob;
use App\Modules\Notificacoes\Models\ConfiguracaoWhatsapp;

class EnviarAvisoTurmaAdapter implements EnviarAvisoTurmaPort
{
    public function canaisDisponiveis(): array
    {
        return [
            self::EMAIL => true,
            self::WHATSAPP => ConfiguracaoWhatsapp::first()?->status === WhatsappGatewayInterface::STATUS_CONNECTED,
        ];
    }

    public function enviar(array $mensagens): void
    {
        if ($mensagens === []) {
            return;
        }

        EnviarAvisoTurmaJob::dispatch((int) InstituicaoContext::id(), $mensagens);
    }
}
