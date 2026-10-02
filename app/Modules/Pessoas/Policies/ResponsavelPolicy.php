<?php

namespace App\Modules\Pessoas\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Auth\Access\Response;

class ResponsavelPolicy
{
    public function view(User $user, Responsavel $responsavel): Response
    {
        return $this->somenteOProprio($user, $responsavel, 'Acesso negado: Você não pode visualizar o perfil de outro responsável.');
    }

    public function update(User $user, Responsavel $responsavel): Response
    {
        return $this->somenteOProprio($user, $responsavel, 'Acesso negado: Você não pode alterar o perfil de outro responsável.');
    }

    private function somenteOProprio(User $user, Responsavel $responsavel, string $mensagem): Response
    {
        if ($user->role !== 'responsavel' || $user->responsavel?->id === $responsavel->id) {
            return Response::allow();
        }

        return Response::deny($mensagem);
    }
}
