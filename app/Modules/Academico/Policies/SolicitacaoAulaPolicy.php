<?php

namespace App\Modules\Academico\Policies;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Core\Support\PermissoesProfessorAulas;
use Illuminate\Auth\Access\Response;

class SolicitacaoAulaPolicy
{
    use VerificaVinculos;

    private const DECISORES = ['admin', 'secretaria'];

    private const PROIBIDO = [
        PermissoesProfessorAulas::CRIAR => 'A escola não permite que professores criem aulas.',
        PermissoesProfessorAulas::EDITAR => 'A escola não permite que professores alterem aulas.',
        PermissoesProfessorAulas::EXCLUIR => 'A escola não permite que professores cancelem aulas.',
    ];

    public function create(User $user, string $tipo, ?AulaTurma $aula = null, ?Turma $turma = null): Response
    {
        if ($user->role !== 'professor') {
            return Response::deny('Somente professores enviam solicitações de aula.');
        }

        if ($aula && ! ($this->lecionaPara($user, $aula->id_professor) || $this->lecionaPara($user, $aula->turma?->id_professor))) {
            return Response::deny('Acesso restrito: Você não leciona nesta aula.');
        }

        if ($turma && ! $this->lecionaPara($user, $turma->id_professor)) {
            return Response::deny('Acesso restrito: Você não leciona nesta turma.');
        }

        $acao = SolicitacaoAula::ACAO_DO_TIPO[$tipo];

        return $this->permitirSe(
            PermissoesProfessorAulas::modo($acao) !== PermissoesProfessorAulas::BLOQUEADO,
            self::PROIBIDO[$acao]
        );
    }

    public function delete(User $user, SolicitacaoAula $solicitacao): Response
    {
        return $this->permitirSe(
            $user->role === 'professor' && $this->lecionaPara($user, $solicitacao->id_professor),
            'Você só pode cancelar as solicitações que fez.'
        );
    }

    public function viewAny(User $user): Response
    {
        return $this->permitirSe(
            in_array($user->role, self::DECISORES, true),
            'Somente a secretaria acompanha as solicitações de aula.'
        );
    }

    public function decidir(User $user, SolicitacaoAula $solicitacao): Response
    {
        return $this->permitirSe(
            in_array($user->role, self::DECISORES, true),
            'Somente a secretaria decide sobre solicitações de aula.'
        );
    }
}
