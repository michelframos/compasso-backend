<?php

namespace App\Modules\Core\UseCases\User;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;

class AlterarSenhaUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Encerra as demais sessões do usuário, mantendo apenas o token em uso.
     */
    public function execute(User $user, string $novaSenha, ?int $tokenAtualId = null): User
    {
        return DB::transaction(function () use ($user, $novaSenha, $tokenAtualId) {
            $user->tokens()
                ->when($tokenAtualId !== null, fn ($query) => $query->whereKeyNot($tokenAtualId))
                ->delete();

            return $this->users->update($user, [
                'password' => $novaSenha,
                'deve_trocar_senha' => false,
            ]);
        });
    }
}
