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
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            Espetaculo::query()->visivelPara($user)->whereKey($espetaculo->id)->exists(),
            'Acesso restrito: você não tem turmas nem alunos neste espetáculo.'
        );
    }
}
