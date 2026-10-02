<?php

namespace App\Modules\Pessoas\UseCases\Professor;

use App\Modules\Core\UseCases\Auth\SendPasswordResetCodeUseCase;
use App\Modules\Pessoas\Models\Professor;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnviarAcessoProfessorUseCase
{
    public function __construct(
        private readonly SendPasswordResetCodeUseCase $sendResetCode,
    ) {}

    public function execute(Professor $professor): string
    {
        $user = $professor->usuario;

        if (! $user || ! filled($user->email)) {
            throw new HttpException(422, 'O professor não possui e-mail cadastrado para receber o acesso.');
        }

        $this->sendResetCode->enviarPara($user, conviteDeAcesso: true);

        return $user->email;
    }
}
