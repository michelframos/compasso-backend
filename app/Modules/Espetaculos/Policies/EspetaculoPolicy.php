<?php

namespace App\Modules\Espetaculos\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Espetaculos\Models\Espetaculo;
use Illuminate\Auth\Access\Response;

class EspetaculoPolicy
{
    use VerificaVinculos;

    public function view(User $user, Espetaculo $espetaculo): Response
    {
        if (! in_array($user->role, ['professor', 'aluno', 'responsavel'], true)) {
            return Response::allow();
        }

        return $this->permitirSe(
            Espetaculo::query()->visivelPara($user)->whereKey($espetaculo->id)->exists(),
            $user->role === 'professor'
                ? 'Acesso restrito: você não tem turmas nem alunos neste espetáculo.'
                : 'Acesso restrito: este espetáculo não é seu.'
        );
    }
}
