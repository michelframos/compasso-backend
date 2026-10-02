<?php

namespace App\Modules\Notificacoes\Strategies;

use App\Modules\Core\Domain\Notificacoes\Notificacao;
use App\Modules\Core\Domain\Notificacoes\ResultadoEnvio;
use App\Modules\Core\Domain\Strategies\NotificationStrategyInterface;
use App\Modules\Notificacoes\Jobs\EnviarEmailNotificacaoJob;
use App\Modules\Notificacoes\Models\NotificacaoDisparada;

class EmailNotificationStrategy implements NotificationStrategyInterface
{
    public const ASSUNTO_PADRAO = 'Mensagem da escola';

    public function canal(): string
    {
        return 'email';
    }

    public function enviar(Notificacao $notificacao): ResultadoEnvio
    {
        if (! $notificacao->destino) {
            return ResultadoEnvio::rejeitado('Nenhum e-mail encontrado para o destinatário.');
        }

        if (! filter_var($notificacao->destino, FILTER_VALIDATE_EMAIL)) {
            return ResultadoEnvio::rejeitado('O e-mail do destinatário é inválido.');
        }

        $registro = NotificacaoDisparada::create([
            'configuracao_notificacao_id' => $notificacao->idConfiguracao,
            'referencia_type' => $notificacao->referenciaType,
            'referencia_id' => $notificacao->referenciaId,
            'canal' => $this->canal(),
            'email' => $notificacao->destino,
            'status' => 'pendente',
            'tentativas' => 0,
        ]);

        EnviarEmailNotificacaoJob::dispatch(
            $registro->id,
            $notificacao->destino,
            $notificacao->destinatario,
            $notificacao->assunto ?: self::ASSUNTO_PADRAO,
            $notificacao->mensagem,
        );

        return ResultadoEnvio::enviado('E-mail enviado com sucesso.');
    }
}
