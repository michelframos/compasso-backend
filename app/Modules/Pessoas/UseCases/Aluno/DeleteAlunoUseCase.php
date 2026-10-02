<?php

namespace App\Modules\Pessoas\UseCases\Aluno;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteAlunoUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(Aluno $aluno): void
    {
        if ($aluno->usuario->role !== 'aluno') {
            throw new HttpException(404, 'Aluno não encontrado');
        }

        if ($aluno->matriculas()->exists()) {
            throw new HttpException(403, 'Não é possível excluir o aluno pois ele possui matrículas em turmas.');
        }

        if ($aluno->apresentacoes()->exists()) {
            throw new HttpException(403, 'Não é possível excluir o aluno pois ele participou de apresentações/espetáculos.');
        }

        DB::transaction(function () use ($aluno) {
            $user = $aluno->usuario;
            $aluno->delete();
            if ($user) {
                $this->users->delete($user);
            }
        });
    }
}
