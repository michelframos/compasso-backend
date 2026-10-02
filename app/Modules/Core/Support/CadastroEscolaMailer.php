<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use App\Modules\Core\Mail\CadastroEscolaMail;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Monta e envia o e-mail de confirmação de cadastro usando o template configurado no painel platform.
 */
class CadastroEscolaMailer
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly EmailTemplateRenderer $renderer,
    ) {}

    public function montar(Instituicao $instituicao, User $responsavel, string $codigo): CadastroEscolaMail
    {
        $valores = $this->valores($instituicao, $responsavel, $codigo);

        $template = $this->settings->emailCadastroCorpo();
        $corpo = $this->renderer->renderHtml($template, $valores);

        if (! $this->renderer->contemVariavel($template, 'codigo_ativacao')) {
            $corpo .= sprintf(
                '<p>Seu código de ativação, solicitado no primeiro acesso, é: <strong style="font-size:20px;letter-spacing:4px;">%s</strong></p>',
                e($codigo),
            );
        }

        $assunto = $this->renderer->renderText($this->settings->emailCadastroAssunto(), $valores);

        return new CadastroEscolaMail($assunto, $corpo, $codigo);
    }

    /**
     * Envia o e-mail; falhas são registradas em log para não desfazer o cadastro.
     */
    public function enviar(Instituicao $instituicao, User $responsavel, string $codigo): bool
    {
        try {
            Mail::to($responsavel->email, $responsavel->nome)
                ->send($this->montar($instituicao, $responsavel, $codigo));

            return true;
        } catch (Throwable $e) {
            Log::error('Falha ao enviar e-mail de cadastro da escola.', [
                'id_instituicao' => $instituicao->id,
                'email' => $responsavel->email,
                'erro' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function valores(Instituicao $instituicao, User $responsavel, string $codigo): array
    {
        $trialDias = $instituicao->trial_ends_at !== null
            ? (int) max(0, round(now()->diffInDays($instituicao->trial_ends_at, false)))
            : 0;

        return [
            'escola_nome' => (string) $instituicao->nome_fantasia,
            'escola_cnpj' => $this->formatarCnpj($instituicao->cnpj),
            'responsavel_nome' => (string) $responsavel->nome,
            'responsavel_email' => (string) $responsavel->email,
            'codigo_ativacao' => $codigo,
            'trial_dias' => (string) $trialDias,
            'trial_termina_em' => $instituicao->trial_ends_at?->format('d/m/Y') ?? '',
            'link_login' => rtrim((string) config('app.frontend_url'), '/').'/login',
        ];
    }

    private function formatarCnpj(?string $cnpj): string
    {
        if ($cnpj === null) {
            return '';
        }

        try {
            return (new Cnpj($cnpj))->format();
        } catch (Throwable) {
            return $cnpj;
        }
    }
}
