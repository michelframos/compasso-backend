<?php

namespace App\Modules\Notificacoes\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificacaoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $assunto,
        public readonly string $mensagem,
        public readonly string $escola,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notificacao', text: 'emails.notificacao-texto');
    }
}
