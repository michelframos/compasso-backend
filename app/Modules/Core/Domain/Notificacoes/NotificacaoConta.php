<?php

namespace App\Modules\Core\Domain\Notificacoes;

final readonly class NotificacaoConta
{
    public function __construct(
        public int $contaId,
        public string $destinatario,
        public ?string $destino,
        public string $mensagem,
    ) {}

    public function comMensagem(string $mensagem): self
    {
        return new self($this->contaId, $this->destinatario, $this->destino, $mensagem);
    }
}
