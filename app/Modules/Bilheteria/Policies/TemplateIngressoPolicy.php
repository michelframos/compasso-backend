<?php

namespace App\Modules\Bilheteria\Policies;

use App\Modules\Bilheteria\Models\TemplateIngresso;
use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\Response;

class TemplateIngressoPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function view(User $user, TemplateIngresso $template): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function create(User $user): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function update(User $user, TemplateIngresso $template): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function delete(User $user, TemplateIngresso $template): Response
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
