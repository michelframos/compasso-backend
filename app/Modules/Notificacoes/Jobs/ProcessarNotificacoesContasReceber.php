<?php

namespace App\Modules\Notificacoes\Jobs;

use App\Models\Conta;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Notificacoes\Models\ConfiguracaoNotificacao;
use App\Modules\Notificacoes\Models\NotificacaoDisparada;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessarNotificacoesContasReceber implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected bool $manual;

    protected ?int $idInstituicao;

    public function __construct(bool $manual = false, ?int $idInstituicao = null)
    {
        $this->manual = $manual;
        $this->idInstituicao = $idInstituicao;
    }

    public function handle(): void
    {
        if ($this->idInstituicao !== null) {
            InstituicaoContext::runWith($this->idInstituicao, null, fn () => $this->processar());

            return;
        }

        Instituicao::where('status', Instituicao::STATUS_ATIVO)
            ->pluck('id')
            ->each(fn (int $id) => InstituicaoContext::runWith($id, null, fn () => $this->processar()));
    }

    private function processar(): void
    {
        $this->processarVencimento();
        $this->processarAtraso();
    }

    private function processarVencimento(): void
    {
        $config = ConfiguracaoNotificacao::where('modulo', 'contas_a_receber')
            ->where('tipo', 'vencimento')
            ->where('ativo', true)
            ->first();

        if (!$config) return;

        $isDailyBatch = true;
        $ultimoProcessamento = $config->ultimo_processamento;

        if (!$this->manual) {
            $horarioEnvio = $config->horario_envio ? Carbon::parse($config->horario_envio)->format('H:i') : '08:00';
            $agora = Carbon::now();

            if ($agora->format('H:i') < $horarioEnvio) return;
            
            if ($ultimoProcessamento && $ultimoProcessamento->isToday()) {
                $isDailyBatch = false;
            }
        }

        $horizonte = Carbon::today()->addDays($config->dias_antecedencia);

        $query = Conta::with(['aluno.usuario', 'matricula.aluno.usuario'])
            ->where('notificar', true)
            ->where('status', 'pendente')
            ->whereDate('data_vencimento', '<=', $horizonte);

        if (!$isDailyBatch && $ultimoProcessamento) {
            $query->where(function($q) use ($ultimoProcessamento) {
                $q->where('created_at', '>', $ultimoProcessamento)
                  ->orWhere('updated_at', '>', $ultimoProcessamento);
            });
        }

        $contas = $query->get();

        foreach ($contas as $conta) {
            if (!$conta->notificar) {
                continue;
            }
            $this->despacharNotificacao($config, $conta, 'vencimento');
        }

        if (!$this->manual) {
            $config->update(['ultimo_processamento' => Carbon::now()]);
        }
    }

    private function processarAtraso(): void
    {
        $config = ConfiguracaoNotificacao::where('modulo', 'contas_a_receber')
            ->where('tipo', 'atraso')
            ->where('ativo', true)
            ->first();

        if (!$config) return;

        $isDailyBatch = true;
        $ultimoProcessamento = $config->ultimo_processamento;

        if (!$this->manual) {
            $horarioEnvio = $config->horario_envio ? Carbon::parse($config->horario_envio)->format('H:i') : '08:00';
            $agora = Carbon::now();

            if ($agora->format('H:i') < $horarioEnvio) return;
            
            if ($ultimoProcessamento && $ultimoProcessamento->isToday()) {
                $isDailyBatch = false;
            }
        }

        $query = Conta::with(['aluno.usuario', 'matricula.aluno.usuario'])
            ->where('notificar', true)
            ->where('status', 'vencida')
            ->whereDate('data_vencimento', '<=', Carbon::today()->subDays($config->dias_antecedencia));

        if (!$isDailyBatch && $ultimoProcessamento) {
            $query->where(function($q) use ($ultimoProcessamento) {
                $q->where('created_at', '>', $ultimoProcessamento)
                  ->orWhere('updated_at', '>', $ultimoProcessamento);
            });
        }

        $contas = $query->get();

        foreach ($contas as $conta) {
            if (!$conta->notificar) {
                continue;
            }
            $this->despacharNotificacao($config, $conta, 'atraso');
        }

        if (!$this->manual) {
            $config->update(['ultimo_processamento' => Carbon::now()]);
        }
    }

    private function despacharNotificacao(ConfiguracaoNotificacao $config, Conta $conta, string $tipo): void
    {
        $aluno = $conta->aluno ?? $conta->matricula?->aluno ?? null;
        if (!$aluno) return;

        $usuario = $aluno->usuario;
        if (!$usuario) return;

        $numero = $this->formatarNumeroWhatsapp($usuario->whatsapp ?? $usuario->telefone ?? '');
        if (!$numero) return;

        $ultimoDisparo = NotificacaoDisparada::where('configuracao_notificacao_id', $config->id)
            ->where('referencia_type', Conta::class)
            ->where('referencia_id', $conta->id)
            ->where('numero_whatsapp', $numero)
            ->whereIn('status', ['enviado', 'pendente'])
            ->latest('created_at')
            ->first();

        if ($ultimoDisparo && !$this->manual) {
            $proximoDisparo = Carbon::parse($ultimoDisparo->disparado_em)->addDays($config->intervalo_repeticao);
            if (Carbon::today()->lt($proximoDisparo)) return;

            if ($config->max_repeticoes !== null && $config->max_repeticoes > 0) {
                $total = NotificacaoDisparada::where('configuracao_notificacao_id', $config->id)
                    ->where('referencia_type', Conta::class)
                    ->where('referencia_id', $conta->id)
                    ->where('numero_whatsapp', $numero)
                    ->where('status', 'enviado')
                    ->count();

                if ($total >= $config->max_repeticoes) return;
            }
        }

        $registro = NotificacaoDisparada::create([
            'configuracao_notificacao_id' => $config->id,
            'referencia_type'             => Conta::class,
            'referencia_id'               => $conta->id,
            'numero_whatsapp'             => $numero,
            'status'                      => 'pendente',
            'tentativas'                  => 0,
        ]);

        $diasAtraso = $tipo === 'atraso'
            ? Carbon::parse($conta->data_vencimento)->diffInDays(Carbon::today())
            : 0;

        $mensagem = $this->preencherTemplate($config->template_mensagem ?? '', [
            '{nome}'         => $usuario->nome,
            '{valor}'        => 'R$ ' . number_format($conta->valor, 2, ',', '.'),
            '{vencimento}'   => Carbon::parse($conta->data_vencimento)->format('d/m/Y'),
            '{dias_atraso}'  => $diasAtraso,
        ]);

        EnviarMensagemWhatsappJob::dispatch($registro->id, $numero, $mensagem);
    }

    private function formatarNumeroWhatsapp(string $numero): string
    {
        $apenasNumeros = preg_replace('/\D/', '', $numero);

        // Se tiver 10 ou 11 dígitos (DDD + número), adiciona o DDI 55
        if (strlen($apenasNumeros) === 10 || strlen($apenasNumeros) === 11) {
            return '55' . $apenasNumeros;
        }

        return $apenasNumeros;
    }

    private function preencherTemplate(string $template, array $variaveis): string
    {
        return str_replace(array_keys($variaveis), array_values($variaveis), $template);
    }
}
