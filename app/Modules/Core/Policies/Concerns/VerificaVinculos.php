<?php

namespace App\Modules\Core\Policies\Concerns;

use App\Modules\Core\Models\User;
use App\Modules\Pessoas\Models\ResponsavelAluno;
use Illuminate\Auth\Access\Response;

trait VerificaVinculos
{
    protected function ehOProprioAluno(User $user, ?int $alunoId): bool
    {
        return $alunoId !== null && $user->aluno?->id === $alunoId;
    }

    protected function ehResponsavelPeloAluno(User $user, ?int $alunoId): bool
    {
        $responsavelId = $user->responsavel?->id;

        return $alunoId !== null && $responsavelId !== null && ResponsavelAluno::query()
            ->where('id_responsavel', $responsavelId)
            ->where('id_aluno', $alunoId)
            ->exists();
    }

    protected function lecionaPara(User $user, ?int $professorId): bool
    {
        return $professorId !== null && $user->professor?->id === $professorId;
    }

    protected function permitirSe(bool $condicao, string $mensagem): Response
    {
        return $condicao ? Response::allow() : Response::deny($mensagem);
    }
}
