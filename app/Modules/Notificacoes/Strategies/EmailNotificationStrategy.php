<?php

namespace App\Modules\Notificacoes\Strategies;

use App\Modules\Core\Domain\Notificacoes\NotificacaoConta;
use App\Modules\Core\Domain\Notificacoes\ResultadoEnvio;
use App\Modules\Core\Domain\Strategies\NotificationStrategyInterface;
use Illuminate\Support\Facades\Log;

class EmailNotificationStrategy implements NotificationStrategyInterface
{
    public function canal(): string
    {
        return 'email';
    }

    public function enviar(NotificacaoConta $notificacao): ResultadoEnvio
    {
        if (! $notificacao->destino) {
            return ResultadoEnvio::rejeitado('Nenhum e-mail encontrado para o destinatário.');
        }

        // Envio real de e-mail ainda não implementado: apenas registra em log.
        Log::info("Enviando Email para {$notificacao->destino}: {$notificacao->mensagem}");

        return ResultadoEnvio::enviado('E-mail enviado com sucesso.');
    }
}
