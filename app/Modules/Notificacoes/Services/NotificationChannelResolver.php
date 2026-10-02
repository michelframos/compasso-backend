<?php

namespace App\Modules\Notificacoes\Services;

use App\Modules\Core\Domain\Strategies\NotificationStrategyInterface;
use InvalidArgumentException;

class NotificationChannelResolver
{
    /** @var array<string, NotificationStrategyInterface> */
    private array $strategies = [];

    /**
     * @param  iterable<NotificationStrategyInterface>  $strategies
     */
    public function __construct(iterable $strategies)
    {
        foreach ($strategies as $strategy) {
            $this->strategies[$strategy->canal()] = $strategy;
        }
    }

    public function resolve(string $canal): NotificationStrategyInterface
    {
        return $this->strategies[$canal]
            ?? throw new InvalidArgumentException("Canal de notificação não suportado: {$canal}");
    }

    /**
     * @return list<string>
     */
    public function canais(): array
    {
        return array_keys($this->strategies);
    }
}
