<?php

namespace App\Models;

/**
 * Alias de compatibilidade — implementação em App\Modules\Core\Models\User.
 */
class User extends \App\Modules\Core\Models\User
{
    /** Tokens e relações polimórficas precisam apontar para a mesma classe, senão `tokens()` não os encontra. */
    public function getMorphClass()
    {
        return parent::class;
    }
}
