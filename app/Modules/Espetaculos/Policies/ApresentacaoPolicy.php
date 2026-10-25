<?php

namespace App\Modules\Espetaculos\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Espetaculos\Models\Apresentacao;
use Illuminate\Auth\Access\Response;

class ApresentacaoPolicy
{
    use VerificaVinculos;

    public function view(User $user, Apresentacao $apresentacao): Response
    {
        if ($user->role !== 'professor') {
            return Response::allow();
        }

        return $this->permitirSe(
            self::visivelParaProfessor($user, $apresentacao),
            'Acesso restrito: esta apresentação não é de uma turma sua nem tem alunos seus.'
        );
    }

    public function gerenciarEnsaios(User $user, Apresentacao $apresentacao): Response
    {
        return match ($user->role) {
            'secretaria' => Response::allow(),
            'professor' => $this->permitirSe(
                self::visivelParaProfessor($user, $apresentacao),
                'Acesso restrito: você só agenda ensaios das apresentações das suas turmas ou com alunos seus.'
            ),
            default => Response::deny('Somente professores e a secretaria agendam ensaios.'),
        };
    }

    public static function visivelParaProfessor(User $user, Apresentacao $apresentacao): bool
    {
        return Apresentacao::query()->visivelPara($user)->whereKey($apresentacao->id)->exists();
    }
}
