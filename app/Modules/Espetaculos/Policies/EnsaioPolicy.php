<?php

namespace App\Modules\Espetaculos\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Espetaculos\Models\Ensaio;
use Illuminate\Auth\Access\Response;

class EnsaioPolicy
{
    use VerificaVinculos;

    public function update(User $user, Ensaio $ensaio): Response
    {
        return $this->gerenciar($user, $ensaio, 'Você só pode editar os ensaios que conduz.');
    }

    public function delete(User $user, Ensaio $ensaio): Response
    {
        return $this->gerenciar($user, $ensaio, 'Você só pode excluir os ensaios que conduz.');
    }

    private function gerenciar(User $user, Ensaio $ensaio, string $mensagem): Response
    {
        return match ($user->role) {
            'secretaria' => Response::allow(),
            'professor' => $this->permitirSe($this->lecionaPara($user, $ensaio->id_professor), $mensagem),
            default => Response::deny($mensagem),
        };
    }
}
