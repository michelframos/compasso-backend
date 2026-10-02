<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Auth\ForgotPasswordRequest;
use App\Modules\Core\Http\Requests\Auth\ResetPasswordRequest;
use App\Modules\Core\UseCases\Auth\ResetPasswordUseCase;
use App\Modules\Core\UseCases\Auth\SendPasswordResetCodeUseCase;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly SendPasswordResetCodeUseCase $sendResetCode,
        private readonly ResetPasswordUseCase $resetPassword,
    ) {}

    #[OA\Post(
        path: '/api/forgot-password',
        summary: 'Solicita a redefinição de senha',
        tags: ['Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ForgotPasswordRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Código enviado com sucesso'),
            new OA\Response(response: 404, description: 'Usuário não encontrado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function sendResetLinkEmail(ForgotPasswordRequest $request)
    {
        $this->sendResetCode->execute($request->email);

        return response()->json(['message' => 'Um código de recuperação foi enviado para o seu e-mail.']);
    }

    #[OA\Post(
        path: '/api/reset-password',
        summary: 'Redefine a senha do usuário',
        tags: ['Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ResetPasswordRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Senha redefinida com sucesso'),
            new OA\Response(response: 400, description: 'Token inválido'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function reset(ResetPasswordRequest $request)
    {
        try {
            $this->resetPassword->execute($request->email, $request->token, $request->password);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['message' => 'Sua senha foi redefinida com sucesso.']);
    }
}
