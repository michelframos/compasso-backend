<?php

namespace App\Modules\Pessoas\Adapters;

use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\Concerns\VerificaVinculos;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlunoAlvoResolverAdapter implements AlunoAlvoResolverPort
{
    use VerificaVinculos;

    public function resolver(User $user, ?int $idAluno = null): Aluno
    {
        if ($user->role === 'aluno') {
            $aluno = $user->aluno;

            if ($aluno === null || ($idAluno !== null && $idAluno !== $aluno->id)) {
                throw new HttpException(403, 'Acesso restrito: você só consulta o próprio cadastro.');
            }

            return $aluno;
        }

        if ($user->role !== 'responsavel') {
            throw new HttpException(403, 'Acesso restrito à área do aluno.');
        }

        if ($idAluno === null) {
            throw ValidationException::withMessages([
                'id_aluno' => 'Informe o aluno para consultar a área.',
            ]);
        }

        if (! $this->ehResponsavelPeloAluno($user, $idAluno)) {
            throw new HttpException(403, 'Acesso restrito: você não é responsável por este aluno.');
        }

        $aluno = Aluno::query()->find($idAluno);

        if ($aluno === null) {
            throw new HttpException(403, 'Acesso restrito: você não é responsável por este aluno.');
        }

        return $aluno;
    }
}
