<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use Illuminate\Auth\Access\Response;

class SugestaoProgressaoPolicy
{
    use VerificaVinculos;

    private const DECISORES = ['admin', 'secretaria'];

    public function create(User $user, Matricula $matricula): Response
    {
        if ($user->role !== 'professor') {
            return Response::deny('Somente professores sugerem progressão de nível.');
        }

        return $this->permitirSe(
            $this->lecionaPara($user, $matricula->idProfessorResponsavel()),
            'Acesso restrito: Esta matrícula não pertence a uma turma ou curso que você leciona.'
        );
    }

    public function delete(User $user, SugestaoProgressao $sugestao): Response
    {
        return $this->permitirSe(
            $user->role === 'professor' && $this->lecionaPara($user, $sugestao->id_professor),
            'Você só pode cancelar as sugestões que fez.'
        );
    }

    public function viewAny(User $user): Response
    {
        return $this->permitirSe(
            in_array($user->role, self::DECISORES, true),
            'Somente a secretaria acompanha as sugestões de progressão.'
        );
    }

    public function decidir(User $user, SugestaoProgressao $sugestao): Response
    {
        return $this->permitirSe(
            in_array($user->role, self::DECISORES, true),
            'Somente a secretaria decide sobre sugestões de progressão.'
        );
    }
}
