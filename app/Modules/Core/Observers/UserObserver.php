<?php

namespace App\Modules\Core\Observers;

use App\Modules\Core\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        // Perfis (Aluno/Professor/Responsavel) são criados nos controllers de Pessoas.
    }

    public function updated(User $user): void
    {
        //
    }

    public function deleted(User $user): void
    {
        //
    }

    public function restored(User $user): void
    {
        //
    }

    public function forceDeleted(User $user): void
    {
        //
    }
}
