<?php

namespace App\Modules\Notificacoes\Jobs;

use App\Models\AulaTurma;
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
use Illuminate\Support\Facades\Log;

class ProcessarNotificacoesAgendamentos implements ShouldQueue
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
        try {
            $config = ConfiguracaoNotificacao::where('modulo', 'agendamentos')
                ->where('ativo', true)
                ->first();

            if (!$config) {
                return;
            }

            $isDailyBatch = true;
            $ultimoProcessamento = $config->ultimo_processamento;

            if (!$this->manual) {
                $horarioEnvio = $config->horario_envio ? Carbon::parse($config->horario_envio)->format('H:i') : '08:00';
                $agora = Carbon::now();

                // Se ainda não chegou no horário de envio, pula
                if ($agora->format('H:i') < $horarioEnvio) {
                    return;
                }

                // Determinar se é o lote diário ou processamento incremental
                if ($ultimoProcessamento && $ultimoProcessamento->isToday()) {
                    $isDailyBatch = false;
                }
            }

            $horizonte = Carbon::today()->addDays($config->dias_antecedencia);

            $query = AulaTurma::with(['turma.matriculas.aluno.usuario', 'aluno_especifico.usuario', 'curso', 'turma.curso'])
                ->where('notificar', true)
                ->whereDate('data', '<=', $horizonte);

            // Se for processamento incremental, busca apenas o que foi criado/alterado após o último envio
            if (!$isDailyBatch && $ultimoProcessamento) {
                $query->where(function($q) use ($ultimoProcessamento) {
                    $q->where('created_at', '>', $ultimoProcessamento)
                      ->orWhere('updated_at', '>', $ultimoProcessamento);
                });
            }

            $agendamentos = $query->get();

            if ($agendamentos->isEmpty()) {
                if (!$this->manual) {
                    $config->update(['ultimo_processamento' => Carbon::now()]);
                }
                return;
            }

            Log::info("Processando " . $agendamentos->count() . " agendamentos para notificações.");

            foreach ($agendamentos as $agendamento) {
                if (!$agendamento->notificar) {
                    continue;
                }

                $destinatarios = $this->resolverDestinatarios($agendamento);

                foreach ($destinatarios as ['numero' => $numero, 'nome' => $nome, 'curso' => $curso]) {
                    // Verificar se já foi disparado recentemente
                    $ultimoDisparo = NotificacaoDisparada::where('configuracao_notificacao_id', $config->id)
                        ->where('referencia_type', AulaTurma::class)
                        ->where('referencia_id', $agendamento->id)
                        ->where('numero_whatsapp', $numero)
                        ->whereIn('status', ['enviado', 'pendente'])
                        ->latest('created_at')
                        ->first();

                    if ($ultimoDisparo && !$this->manual) {
                        $proximoDisparo = Carbon::parse($ultimoDisparo->disparado_em)->addDays($config->intervalo_repeticao);
                        if (Carbon::today()->lt($proximoDisparo)) {
                            continue;
                        }

                        // Verificar limite de repetições
                        if ($config->max_repeticoes !== null && $config->max_repeticoes > 0) {
                            $totalDisparos = NotificacaoDisparada::where('configuracao_notificacao_id', $config->id)
                                ->where('referencia_type', AulaTurma::class)
                                ->where('referencia_id', $agendamento->id)
                                ->where('numero_whatsapp', $numero)
                                ->where('status', 'enviado')
                                ->count();

                            if ($totalDisparos >= $config->max_repeticoes) {
                                continue;
                            }
                        }
                    }

                    $registro = NotificacaoDisparada::create([
                        'configuracao_notificacao_id' => $config->id,
                        'referencia_type'             => AulaTurma::class,
                        'referencia_id'               => $agendamento->id,
                        'numero_whatsapp'             => $numero,
                        'status'                      => 'pendente',
                        'tentativas'                  => 0,
                    ]);

                    if (!$config->template_mensagem) {
                        Log::warning("Template de mensagem não configurado para o módulo de agendamentos.");
                        continue;
                    }

                    $mensagem = $this->preencherTemplate($config->template_mensagem ?? '', [
                        '{nome}'    => $nome,
                        '{data}'    => Carbon::parse($agendamento->data)->format('d/m/Y'),
                        '{horario}' => $agendamento->hora_inicio ? Carbon::parse($agendamento->hora_inicio)->format('H:i') : '-',
                        '{curso}'   => $curso,
                    ]);

                    Log::info("Mensagem gerada: " . $mensagem);

                    EnviarMensagemWhatsappJob::dispatch($registro->id, $numero, $mensagem);
                }
            }

            if (!$this->manual) {
                $config->update(['ultimo_processamento' => Carbon::now()]);
            }
        } catch (\Exception $e) {
            \Log::error("Erro no Job ProcessarNotificacoesAgendamentos: " . $e->getMessage(), [
                'exception' => $e,
                'stack'     => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function resolverDestinatarios(AulaTurma $agendamento): array
    {
        $destinatarios = [];
        $nomeCurso = $agendamento->curso?->nome ?? $agendamento->turma?->curso?->nome ?? '-';

        if ($agendamento->id_aluno_especifico && $agendamento->aluno_especifico) {
            $aluno = $agendamento->aluno_especifico;
            $usuario = $aluno->usuario;
            $numero = $usuario->whatsapp ?? $usuario->telefone ?? null;
            if ($numero) {
                $destinatarios[] = [
                    'numero' => $this->formatarNumeroWhatsapp($numero),
                    'nome'   => $usuario->nome,
                    'curso'  => $nomeCurso,
                ];
            }
        } elseif ($agendamento->turma) {
            foreach ($agendamento->turma->matriculas()->where('status', 'ativa')->with('aluno.usuario')->get() as $matricula) {
                $aluno = $matricula->aluno;
                if (!$aluno || !$aluno->usuario) continue;
                $usuario = $aluno->usuario;
                $numero = $usuario->whatsapp ?? $usuario->telefone ?? null;
                if ($numero) {
                    $destinatarios[] = [
                        'numero' => $this->formatarNumeroWhatsapp($numero),
                        'nome'   => $usuario->nome,
                        'curso'  => $nomeCurso,
                    ];
                }
            }
        }

        return $destinatarios;
    }

    private function preencherTemplate(string $template, array $variaveis): string
    {
        return str_replace(array_keys($variaveis), array_values($variaveis), $template);
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
}
