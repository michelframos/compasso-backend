<?php

namespace App\Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public bool $conviteDeAcesso = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $link = rtrim((string) config('app.frontend_url'), '/')
            .'/redefinir-senha?'.http_build_query(['email' => $notifiable->email]);

        $mensagem = (new MailMessage)->greeting('Olá!');

        if ($this->conviteDeAcesso) {
            $mensagem->subject('Seu acesso ao Compasso')
                ->line('A secretaria da sua escola liberou o seu acesso ao Compasso.')
                ->line('Use o código abaixo para definir a sua senha:');
        } else {
            $mensagem->subject('Recuperação de Senha - Compasso')
                ->line('Você está recebendo este e-mail porque recebemos uma solicitação de redefinição de senha para a sua conta.')
                ->line('Seu código de segurança é:');
        }

        return $mensagem
            ->line($this->token)
            ->action('Definir senha', $link)
            ->line('Este código expira em 60 minutos.')
            ->line('Se você não reconhece esta solicitação, nenhuma ação adicional é necessária.');
    }
}
