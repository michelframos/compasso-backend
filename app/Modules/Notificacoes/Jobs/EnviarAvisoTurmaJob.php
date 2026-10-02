<?php

namespace App\Modules\Notificacoes\Jobs;

use App\Modules\Core\Domain\Notificacoes\Notificacao;
use App\Modules\Core\Events\NotificacaoProcessada;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Notificacoes\Services\NotificationChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/** Distribui um aviso pelos canais; cada mensagem vira um disparo com job próprio. */
class EnviarAvisoTurmaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @param  list<array{canal: string, notificacao: Notificacao}>  $mensagens */
    public function __construct(
        private readonly int $idInstituicao,
        private readonly array $mensagens,
    ) {}

    public function handle(NotificationChannelResolver $canais): void
    {
        InstituicaoContext::runWith($this->idInstituicao, null, function () use ($canais): void {
            foreach ($this->mensagens as ['canal' => $canal, 'notificacao' => $notificacao]) {
                $this->enviar($canais, $canal, $notificacao);
            }
        });
    }

    private function enviar(NotificationChannelResolver $canais, string $canal, Notificacao $notificacao): void
    {
        try {
            $resultado = $canais->resolve($canal)->enviar($notificacao);
        } catch (Throwable $e) {
            report($e);
            $this->falhou($notificacao, 'Falha ao enviar a mensagem.');

            return;
        }

        if (! $resultado->sucesso()) {
            $this->falhou($notificacao, $resultado->mensagem);
        }
    }

    private function falhou(Notificacao $notificacao, string $erro): void
    {
        NotificacaoProcessada::dispatch($notificacao->referenciaType, $notificacao->referenciaId, NotificacaoProcessada::ERRO, $erro);
    }
}
