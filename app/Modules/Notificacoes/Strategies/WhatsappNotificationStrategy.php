<?php

namespace App\Modules\Notificacoes\Strategies;

use App\Modules\Core\Domain\Notificacoes\Notificacao;
use App\Modules\Core\Domain\Notificacoes\ResultadoEnvio;
use App\Modules\Core\Domain\Strategies\NotificationStrategyInterface;
use App\Modules\Notificacoes\Contracts\WhatsappGatewayInterface;
use App\Modules\Notificacoes\Jobs\EnviarMensagemWhatsappJob;
use App\Modules\Notificacoes\Models\ConfiguracaoWhatsapp;
use App\Modules\Notificacoes\Models\NotificacaoDisparada;

class WhatsappNotificationStrategy implements NotificationStrategyInterface
{
    public function canal(): string
    {
        return 'whatsapp';
    }

    public function enviar(Notificacao $notificacao): ResultadoEnvio
    {
        if (! $notificacao->destino) {
            return ResultadoEnvio::rejeitado('Nenhum número de WhatsApp encontrado para o destinatário.');
        }

        $configWhatsapp = ConfiguracaoWhatsapp::first();
        if (! $configWhatsapp || $configWhatsapp->status !== WhatsappGatewayInterface::STATUS_CONNECTED) {
            return ResultadoEnvio::rejeitado('WhatsApp não está conectado. Conecte em Configurações antes de enviar.');
        }

        $registro = NotificacaoDisparada::create([
            'configuracao_notificacao_id' => $notificacao->idConfiguracao,
            'referencia_type' => $notificacao->referenciaType,
            'referencia_id' => $notificacao->referenciaId,
            'canal' => $this->canal(),
            'numero_whatsapp' => $notificacao->destino,
            'status' => 'pendente',
            'tentativas' => 0,
        ]);

        EnviarMensagemWhatsappJob::dispatch($registro->id, $notificacao->destino, $notificacao->mensagem);

        return ResultadoEnvio::enviado('Mensagem de WhatsApp enfileirada com sucesso.');
    }
}
