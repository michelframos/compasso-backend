<?php

namespace App\Modules\Notificacoes\Strategies;

use App\Modules\Core\Domain\Notificacoes\NotificacaoConta;
use App\Modules\Core\Domain\Notificacoes\ResultadoEnvio;
use App\Modules\Core\Domain\Strategies\NotificationStrategyInterface;
use App\Modules\Notificacoes\Contracts\WhatsappGatewayInterface;
use App\Modules\Notificacoes\Jobs\EnviarMensagemWhatsappJob;
use App\Modules\Notificacoes\Models\ConfiguracaoNotificacao;
use App\Modules\Notificacoes\Models\ConfiguracaoWhatsapp;
use App\Modules\Notificacoes\Models\NotificacaoDisparada;
use App\Modules\Notificacoes\Services\MontarNotificacaoContaService;

class WhatsappNotificationStrategy implements NotificationStrategyInterface
{
    public function canal(): string
    {
        return 'whatsapp';
    }

    public function enviar(NotificacaoConta $notificacao): ResultadoEnvio
    {
        if (! $notificacao->destino) {
            return ResultadoEnvio::rejeitado('Nenhum número de WhatsApp encontrado para o destinatário.');
        }

        $configWhatsapp = ConfiguracaoWhatsapp::first();
        if (! $configWhatsapp || $configWhatsapp->status !== WhatsappGatewayInterface::STATUS_CONNECTED) {
            return ResultadoEnvio::rejeitado('WhatsApp não está conectado. Conecte em Configurações antes de enviar.');
        }

        $config = ConfiguracaoNotificacao::firstOrCreate(
            [
                'modulo' => 'contas_a_receber',
                'tipo' => 'atraso',
            ],
            [
                'ativo' => false,
                'dias_antecedencia' => 1,
                'intervalo_repeticao' => 1,
                'max_repeticoes' => null,
                'template_mensagem' => MontarNotificacaoContaService::TEMPLATE_PADRAO_ATRASO,
                'horario_envio' => '08:00',
            ]
        );

        // Os jobs de notificação automática identificam a conta pelo alias legado.
        $registro = NotificacaoDisparada::create([
            'configuracao_notificacao_id' => $config->id,
            'referencia_type' => \App\Models\Conta::class,
            'referencia_id' => $notificacao->contaId,
            'numero_whatsapp' => $notificacao->destino,
            'status' => 'pendente',
            'tentativas' => 0,
        ]);

        EnviarMensagemWhatsappJob::dispatch($registro->id, $notificacao->destino, $notificacao->mensagem);

        return ResultadoEnvio::enviado('Mensagem de WhatsApp enfileirada com sucesso.');
    }
}
