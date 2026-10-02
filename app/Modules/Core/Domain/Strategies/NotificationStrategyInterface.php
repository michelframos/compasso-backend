<?php

namespace App\Modules\Core\Domain\Strategies;

use App\Modules\Core\Domain\Notificacoes\NotificacaoConta;
use App\Modules\Core\Domain\Notificacoes\ResultadoEnvio;

interface NotificationStrategyInterface
{
    public function canal(): string;

    public function enviar(NotificacaoConta $notificacao): ResultadoEnvio;
}
