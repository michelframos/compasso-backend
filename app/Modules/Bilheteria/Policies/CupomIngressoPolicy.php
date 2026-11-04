<?php

namespace App\Modules\Bilheteria\Policies;

use App\Modules\Bilheteria\Models\CupomIngresso;
use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\Response;

class CupomIngressoPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function view(User $user, CupomIngresso $cupom): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function create(User $user): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function update(User $user, CupomIngresso $cupom): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function delete(User $user, CupomIngresso $cupom): Response
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
