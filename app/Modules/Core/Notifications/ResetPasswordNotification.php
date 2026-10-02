<?php

namespace App\Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('RecuperaÃ§Ã£o de Senha - Camerata')
                    ->greeting('OlÃ¡!')
                    ->line('VocÃª estÃ¡ recebendo este e-mail porque recebemos uma solicitaÃ§Ã£o de redefiniÃ§Ã£o de senha para a sua conta.')
                    ->line('Seu cÃ³digo de seguranÃ§a Ã©:')
                    ->line((string) $this->token)
                    ->line('Este cÃ³digo expira em 60 minutos.')
                    ->line('Se vocÃª nÃ£o solicitou uma redefiniÃ§Ã£o de senha, nenhuma aÃ§Ã£o adicional Ã© necessÃ¡ria.');
    }
}
