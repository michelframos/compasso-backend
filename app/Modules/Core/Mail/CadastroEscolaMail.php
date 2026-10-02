<?php

namespace App\Modules\Core\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CadastroEscolaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $assunto,
        public readonly string $corpoHtml,
        public readonly string $codigoAtivacao,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.cadastro-escola');
    }
}
