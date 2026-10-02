<?php

namespace App\Modules\Core\UseCases\Auth;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoTokenService;

class RegisterUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly InstituicaoTokenService $tokenService,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function execute(array $data): array
    {
        $user = $this->users->create($data);

        $instituicao = Instituicao::query()->where('slug', 'default')->first();

        if ($instituicao !== null) {
            InstituicaoUsuario::query()->firstOrCreate(
                [
                    'id_instituicao' => $instituicao->id,
                    'id_usuario' => $user->id,
                ],
                [
                    'role' => $user->role,
                    'status' => InstituicaoUsuario::STATUS_ATIVO,
                ]
            );
        }

        $token = $this->tokenService->create($user, $instituicao)->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }
}
