<?php

namespace App\Modules\Core\Domain\Notificacoes;

final readonly class ResultadoEnvio
{
    public const ENVIADO = 'enviado';
    public const REJEITADO = 'rejeitado';
    public const FALHOU = 'falhou';

    private function __construct(
        public string $status,
        public string $mensagem,
    ) {}

    public static function enviado(string $mensagem): self
    {
        return new self(self::ENVIADO, $mensagem);
    }

    /**
     * Pré-condição não atendida (ex.: destinatário sem contato, canal desconectado).
     */
    public static function rejeitado(string $mensagem): self
    {
        return new self(self::REJEITADO, $mensagem);
    }

    public static function falhou(string $mensagem): self
    {
        return new self(self::FALHOU, $mensagem);
    }

    public function sucesso(): bool
    {
        return $this->status === self::ENVIADO;
    }
}
