<?php

namespace App\Modules\Financeiro\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Financeiro\Models\Conta;
use Illuminate\Auth\Access\Response;

class ContaPolicy
{
    use VerificaVinculos;

    /** Também autoriza a listagem e o pagamento (individual ou em lote) da conta. */
    public function view(User $user, Conta $conta): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $conta->id_aluno),
                'Acesso Restrito: Esta conta não pertence a você.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $conta->id_aluno),
                'Acesso Restrito: Você não é responsável pelo aluno vinculado a esta conta.'
            ),
            default => Response::allow(),
        };
    }

    public function pagar(User $user, Conta $conta): Response
    {
        return $this->view($user, $conta);
    }
}
