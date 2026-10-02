<?php

namespace App\Modules\Notificacoes\Jobs;

use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Notificacoes\Contracts\WhatsappGatewayInterface;
use App\Modules\Notificacoes\Exceptions\WhatsappGatewayException;
use App\Modules\Notificacoes\Models\ConfiguracaoWhatsapp;
use App\Modules\Notificacoes\Models\NotificacaoDisparada;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnviarMensagemWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly int $notificacaoId,
        private readonly string $numero,
        private readonly string $mensagem,
    ) {}

    public function handle(WhatsappGatewayInterface $whatsapp): void
    {
        $registro = NotificacaoDisparada::withoutInstituicaoScope()->find($this->notificacaoId);
        if (!$registro) {
            return;
        }

        InstituicaoContext::runWith($registro->id_instituicao, null, function () use ($registro, $whatsapp): void {
            $this->enviar($registro, $whatsapp);
        });
    }

    private function enviar(NotificacaoDisparada $registro, WhatsappGatewayInterface $whatsapp): void
    {
        $registro->increment('tentativas');

        $config = ConfiguracaoWhatsapp::first();

        if (!$config || $config->status !== WhatsappGatewayInterface::STATUS_CONNECTED) {
            $registro->update(['status' => 'erro']);
            Log::warning("WhatsApp não conectado. Notificação #{$this->notificacaoId} marcada como erro.");
            return;
        }

        try {
            $whatsapp->sendText($config->instance_name, $this->numero, $this->mensagem);
        } catch (WhatsappGatewayException $e) {
            $registro->update(['status' => 'erro']);
            Log::error("Erro ao enviar WhatsApp #{$this->notificacaoId}: " . $e->getMessage());

            if ($e->retryable) {
                throw $e;
            }

            return;
        }

        $registro->update([
            'status'       => 'enviado',
            'disparado_em' => Carbon::now(),
        ]);
    }
}
