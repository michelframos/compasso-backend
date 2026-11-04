<?php

namespace App\Modules\Espetaculos\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Espetaculos\Models\Apresentacao;
use Illuminate\Auth\Access\Response;

class ApresentacaoPolicy
{
    use VerificaVinculos;

    public function viewAnyDoAluno(User $user, int $alunoId): Response
    {
        return match ($user->role) {
            'aluno' => $this->permitirSe(
                $this->ehOProprioAluno($user, $alunoId),
                'Acesso restrito: você só pode ver as suas apresentações.'
            ),
            'responsavel' => $this->permitirSe(
                $this->ehResponsavelPeloAluno($user, $alunoId),
                'Acesso restrito: você não é o responsável por este aluno.'
            ),
            default => Response::deny('Acesso restrito: apenas o aluno ou o responsável consulta as apresentações.'),
        };
    }

    public function view(User $user, Apresentacao $apresentacao): Response
    {
        if (! in_array($user->role, ['professor', 'aluno', 'responsavel'], true)) {
            return Response::allow();
        }

        return $this->permitirSe(
            self::visivelParaProfessor($user, $apresentacao),
            $user->role === 'professor'
                ? 'Acesso restrito: esta apresentação não é de uma turma sua nem tem alunos seus.'
                : 'Acesso restrito: esta apresentação não é sua.'
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
