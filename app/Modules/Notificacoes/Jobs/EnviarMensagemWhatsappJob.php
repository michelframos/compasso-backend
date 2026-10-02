<?php

namespace App\Modules\Notificacoes\Jobs;

use App\Modules\Core\Events\NotificacaoProcessada;
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
            $this->marcarErro($registro, 'WhatsApp não está conectado.');
            Log::warning("WhatsApp não conectado. Notificação #{$this->notificacaoId} marcada como erro.");
            return;
        }

        try {
            $whatsapp->sendText($config->instance_name, $this->numero, $this->mensagem);
        } catch (WhatsappGatewayException $e) {
            $this->marcarErro($registro, 'Falha ao enviar pelo WhatsApp.');
            Log::error("Erro ao enviar WhatsApp #{$this->notificacaoId}: " . $e->getMessage());

            if ($e->retryable) {
                throw $e;
            }

            return;
        }

        $registro->update([
            'status'       => 'enviado',
            'erro'         => null,
            'disparado_em' => Carbon::now(),
        ]);
        NotificacaoProcessada::dispatch($registro->referencia_type, $registro->referencia_id, NotificacaoProcessada::ENVIADO);
    }

    private function marcarErro(NotificacaoDisparada $registro, string $erro): void
    {
        $registro->update(['status' => 'erro', 'erro' => $erro]);
        NotificacaoProcessada::dispatch($registro->referencia_type, $registro->referencia_id, NotificacaoProcessada::ERRO, $erro);
    }
}
