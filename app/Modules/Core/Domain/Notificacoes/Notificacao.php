<?php

namespace App\Modules\Core\Domain\Notificacoes;

/**
 * Mensagem pronta para um destinatário, independente do canal.
 * A referência aponta o registro de origem (conta, destinatário de aviso...) e volta no evento NotificacaoProcessada.
 */
final readonly class Notificacao
{
    public function __construct(
        public string $destinatario,
        public ?string $destino,
        public string $mensagem,
        public string $referenciaType,
        public int $referenciaId,
        public ?string $assunto = null,
        public ?int $idConfiguracao = null,
    ) {}
}
