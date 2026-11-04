<?php

namespace App\Modules\Pessoas\UseCases\Responsavel;

use App\Modules\Core\UseCases\Auth\SendPasswordResetCodeUseCase;
use App\Modules\Pessoas\Models\Responsavel;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnviarAcessoResponsavelUseCase
{
    public function __construct(
        private readonly SendPasswordResetCodeUseCase $sendResetCode,
    ) {}

    public function execute(Responsavel $responsavel): string
    {
        $user = $responsavel->usuario;

        if (! $user || ! filled($user->email)) {
            throw new HttpException(422, 'O responsável não possui e-mail cadastrado para receber o acesso.');
        }

        $this->sendResetCode->enviarPara($user, conviteDeAcesso: true);

        return $user->email;
    }
}
