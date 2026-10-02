<?php

namespace App\Modules\Core\UseCases\Auth;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Models\User;
use App\Modules\Core\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SendPasswordResetCodeUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(string $email): void
    {
        $user = $this->users->findByEmail($email);
        if (! $user) {
            throw new HttpException(404, 'Usuário não encontrado');
        }

        $this->enviarPara($user);
    }

    public function enviarPara(User $user, bool $conviteDeAcesso = false): void
    {
        $token = sprintf('%06d', random_int(1, 999999));

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $user->notify(new ResetPasswordNotification($token, $conviteDeAcesso));
    }
}
