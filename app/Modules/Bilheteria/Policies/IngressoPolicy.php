<?php

namespace App\Modules\Bilheteria\Policies;

use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\Response;

class IngressoPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function view(User $user, Ingresso $ingresso): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function create(User $user): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function update(User $user, Ingresso $ingresso): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function delete(User $user, Ingresso $ingresso): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    private function secretariaOuAdmin(User $user): Response
    {
        return in_array($user->role, ['secretaria', 'admin'], true)
            ? Response::allow()
            : Response::deny('Acesso restrito à secretaria.');
    }
}
