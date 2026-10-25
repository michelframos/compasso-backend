<?php

namespace App\Modules\Core\Domain\Strategies;

use App\Modules\Core\Domain\Notificacoes\Notificacao;
use App\Modules\Core\Domain\Notificacoes\ResultadoEnvio;

interface NotificationStrategyInterface
{
    public function canal(): string;

    public function enviar(Notificacao $notificacao): ResultadoEnvio;
}
