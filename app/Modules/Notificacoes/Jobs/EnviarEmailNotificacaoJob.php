<?php

namespace App\Modules\Notificacoes\Jobs;

use App\Modules\Core\Events\NotificacaoProcessada;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Notificacoes\Mail\NotificacaoMail;
use App\Modules\Notificacoes\Models\NotificacaoDisparada;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnviarEmailNotificacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly int $notificacaoId,
        private readonly string $email,
        private readonly string $nome,
        private readonly string $assunto,
        private readonly string $mensagem,
    ) {}

    public function handle(): void
    {
        $registro = NotificacaoDisparada::withoutInstituicaoScope()->find($this->notificacaoId);
        if (! $registro) {
            return;
        }

        InstituicaoContext::runWith($registro->id_instituicao, null, function () use ($registro): void {
            $this->enviar($registro);
        });
    }

    private function enviar(NotificacaoDisparada $registro): void
    {
        $registro->increment('tentativas');
        $escola = Instituicao::query()->find($registro->id_instituicao)?->nome_fantasia ?? config('app.name');

        try {
            Mail::to($this->email, $this->nome)->send(new NotificacaoMail($this->assunto, $this->mensagem, $escola));
        } catch (Throwable $e) {
            Log::error("Erro ao enviar e-mail #{$this->notificacaoId}: ".$e->getMessage());
            $registro->update(['status' => 'erro', 'erro' => 'Falha ao enviar o e-mail.']);
            NotificacaoProcessada::dispatch($registro->referencia_type, $registro->referencia_id, NotificacaoProcessada::ERRO, 'Falha ao enviar o e-mail.');

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff);
            }

            return;
        }

        $registro->update(['status' => 'enviado', 'erro' => null, 'disparado_em' => Carbon::now()]);
        NotificacaoProcessada::dispatch($registro->referencia_type, $registro->referencia_id, NotificacaoProcessada::ENVIADO);
    }
}
