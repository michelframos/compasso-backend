<?php

namespace App\Modules\Espetaculos\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Espetaculos\Models\ApresentacaoAluno;
use Illuminate\Auth\Access\Response;

class ApresentacaoAlunoPolicy
{
    use VerificaVinculos;

    public function view(User $user, ApresentacaoAluno $participacao): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            ApresentacaoAluno::query()->visivelPara($user)->whereKey($participacao->id)->exists(),
            'Acesso restrito: esta participação é de uma apresentação que não envolve você.'
        );
    }
}
