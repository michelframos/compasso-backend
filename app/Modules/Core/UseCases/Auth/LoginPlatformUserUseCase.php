<?php

namespace App\Modules\Core\UseCases\Auth;

use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoTokenService;
use Illuminate\Support\Facades\Hash;

class LoginPlatformUserUseCase
{
    public function __construct(
        private readonly InstituicaoTokenService $tokenService,
    ) {}

    /**
     * @return array{user: User, token: string}|null
     */
    public function execute(string $email, string $password): ?array
    {
        $superAdmin = User::query()
            ->where('email', $email)
            ->where('is_super_admin', true)
            ->get()
            ->first(fn (User $user): bool => Hash::check($password, $user->senha));

        if ($superAdmin === null) {
            return null;
        }

        return [
            'user' => $superAdmin,
            'token' => $this->tokenService->create($superAdmin, null)->plainTextToken,
        ];
    }
}
