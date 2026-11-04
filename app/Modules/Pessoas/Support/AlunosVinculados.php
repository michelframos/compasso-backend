<?php

namespace App\Modules\Pessoas\Support;

use App\Modules\Core\Models\User;
use App\Modules\Pessoas\Models\Aluno;
use App\Modules\Pessoas\Models\ResponsavelAluno;
use Illuminate\Database\Eloquent\Builder;

/** Subconsulta dos alunos que o usuário pode consultar: o próprio ou os dependentes. */
final class AlunosVinculados
{
    /** @return Builder<Aluno>|Builder<ResponsavelAluno> */
    public static function ids(User $user): Builder
    {
        if ($user->role === 'responsavel') {
            return ResponsavelAluno::query()
                ->where('id_responsavel', $user->responsavel?->id ?? 0)
                ->select('id_aluno');
        }

        return Aluno::query()->whereKey($user->aluno?->id ?? 0)->select('id');
    }
}
