<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Core\Models\User;
use App\Modules\Pessoas\Models\Aluno;

interface AlunoAlvoResolverPort
{
    /**
     * Aluno: o próprio cadastro. Responsável: exige id_aluno e o vínculo na instituição.
     */
    public function resolver(User $user, ?int $idAluno = null): Aluno;
}
