<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\TemplateCertificado;
use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\Response;

class TemplateCertificadoPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function view(User $user, TemplateCertificado $template): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function create(User $user): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function update(User $user, TemplateCertificado $template): Response
    {
        return $this->secretariaOuAdmin($user);
    }

    public function delete(User $user, TemplateCertificado $template): Response
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
