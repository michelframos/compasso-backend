<?php

namespace App\Modules\Notificacoes\Services;

use App\Modules\Core\Domain\Notificacoes\Notificacao;
use App\Modules\Core\Domain\Notificacoes\NotificacaoConta;
use App\Modules\Core\Support\NumeroWhatsapp;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Notificacoes\Models\ConfiguracaoNotificacao;
use Carbon\Carbon;

class MontarNotificacaoContaService
{
    public const TEMPLATE_PADRAO_ATRASO = 'Olá {nome}. Identificamos um atraso de {dias_atraso} dias na sua parcela de {valor} com vencimento em {vencimento}.';

    public function montar(Conta $conta, string $canal): NotificacaoConta
    {
        $conta->loadMissing(['aluno.usuario', 'aluno.responsaveis.usuario']);

        $aluno = $conta->aluno;
        $responsavel = $aluno?->responsaveis?->first();
        $usuarioDestino = $responsavel?->usuario ?? $aluno?->usuario;

        $nome = $usuarioDestino?->nome
            ?? $aluno?->usuario?->nome
            ?? 'Cliente';

        $destino = $canal === 'whatsapp'
            ? NumeroWhatsapp::formatar(
                $usuarioDestino?->whatsapp
                    ?? $usuarioDestino?->telefone
                    ?? $aluno?->usuario?->whatsapp
                    ?? $aluno?->usuario?->telefone
            )
            : ($usuarioDestino?->email ?? $aluno?->usuario?->email ?? '');

        return new NotificacaoConta(
            contaId: $conta->id,
            destinatario: $nome,
            destino: $destino ?: null,
            mensagem: $this->renderizarMensagem($conta, $nome),
        );
    }

    /** Os jobs de notificação automática identificam a conta pelo alias legado. */
    public function paraEnvio(NotificacaoConta $notificacao): Notificacao
    {
        return new Notificacao(
            destinatario: $notificacao->destinatario,
            destino: $notificacao->destino,
            mensagem: $notificacao->mensagem,
            referenciaType: \App\Models\Conta::class,
            referenciaId: $notificacao->contaId,
            assunto: 'Aviso sobre sua parcela',
            idConfiguracao: $this->configuracaoAtraso()->id,
        );
    }

    private function configuracaoAtraso(): ConfiguracaoNotificacao
    {
        return ConfiguracaoNotificacao::firstOrCreate(
            [
                'modulo' => 'contas_a_receber',
                'tipo' => 'atraso',
            ],
            [
                'ativo' => false,
                'dias_antecedencia' => 1,
                'intervalo_repeticao' => 1,
                'max_repeticoes' => null,
                'template_mensagem' => self::TEMPLATE_PADRAO_ATRASO,
                'horario_envio' => '08:00',
            ]
        );
    }

    private function renderizarMensagem(Conta $conta, string $nome): string
    {
        $config = ConfiguracaoNotificacao::where('modulo', 'contas_a_receber')
            ->where('tipo', 'atraso')
            ->first();

        $template = trim((string) ($config?->template_mensagem ?: '')) ?: self::TEMPLATE_PADRAO_ATRASO;
        $vencimento = Carbon::parse($conta->data_vencimento);
        $diasAtraso = max(0, (int) $vencimento->diffInDays(Carbon::today()));

        return str_replace(
            ['{nome}', '{valor}', '{vencimento}', '{dias_atraso}'],
            [
                $nome,
                'R$ '.number_format((float) $conta->valor, 2, ',', '.'),
                $vencimento->format('d/m/Y'),
                (string) $diasAtraso,
            ],
            $template
        );
    }
}
