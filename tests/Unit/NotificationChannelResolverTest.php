<?php

namespace Tests\Unit;

use App\Modules\Core\Domain\Notificacoes\NotificacaoConta;
use App\Modules\Core\Domain\Notificacoes\ResultadoEnvio;
use App\Modules\Core\Domain\Strategies\NotificationStrategyInterface;
use App\Modules\Notificacoes\Services\NotificationChannelResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NotificationChannelResolverTest extends TestCase
{
    private function estrategia(string $canal): NotificationStrategyInterface
    {
        return new class($canal) implements NotificationStrategyInterface
        {
            public function __construct(private readonly string $nome) {}

            public function canal(): string
            {
                return $this->nome;
            }

            public function enviar(NotificacaoConta $notificacao): ResultadoEnvio
            {
                return ResultadoEnvio::enviado($this->nome);
            }
        };
    }

    public function test_resolve_estrategia_pelo_canal(): void
    {
        $resolver = new NotificationChannelResolver([$this->estrategia('email'), $this->estrategia('sms')]);

        $this->assertSame(['email', 'sms'], $resolver->canais());
        $this->assertSame('sms', $resolver->resolve('sms')->canal());
    }

    public function test_canal_desconhecido_lanca_excecao(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NotificationChannelResolver([$this->estrategia('email')]))->resolve('pombo');
    }
}
