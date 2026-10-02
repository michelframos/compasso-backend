<?php

namespace App\Modules\Core\UseCases\Auth;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResetPasswordUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(string $email, string $token, string $password): void
    {
        $resetRecord = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (
            ! $resetRecord
            || ! Hash::check($token, $resetRecord->token)
            || Carbon::parse($resetRecord->created_at)->addMinutes(60)->isPast()
        ) {
            throw new HttpException(400, 'O código de segurança fornecido é inválido ou expirou.');
        }

        $user = $this->users->findByEmail($email);
        if (! $user) {
            throw new HttpException(404, 'Usuário não encontrado');
        }

        $this->users->update($user, ['password' => $password]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }
}
