<?php

namespace App\Modules\Pessoas\UseCases\Aluno;

use App\Modules\Core\UseCases\Auth\SendPasswordResetCodeUseCase;
use App\Modules\Pessoas\Models\Aluno;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnviarAcessoAlunoUseCase
{
    public function __construct(
        private readonly SendPasswordResetCodeUseCase $sendResetCode,
    ) {}

    public function execute(Aluno $aluno): string
    {
        $user = $aluno->usuario;

        if (! $user || ! filled($user->email)) {
            throw new HttpException(422, 'O aluno não possui e-mail cadastrado para receber o acesso.');
        }

        $this->sendResetCode->enviarPara($user, conviteDeAcesso: true);

        return $user->email;
    }
}
