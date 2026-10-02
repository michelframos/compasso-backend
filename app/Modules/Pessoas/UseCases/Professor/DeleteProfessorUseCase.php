<?php

namespace App\Modules\Pessoas\UseCases\Professor;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Pessoas\Models\Professor;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteProfessorUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(Professor $professor): void
    {
        if ($professor->turmas()->exists()) {
            throw new HttpException(403, 'Não é possível excluir o professor pois ele está vinculado a turmas.');
        }

        DB::transaction(function () use ($professor) {
            $user = $professor->usuario;
            $professor->delete();
            if ($user) {
                $this->users->delete($user);
            }
        });
    }
}
