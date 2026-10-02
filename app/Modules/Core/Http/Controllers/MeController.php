<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\User\AlterarSenhaRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\UseCases\User\AlterarSenhaUseCase;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;

class MeController extends Controller
{
    public function __construct(
        private readonly AlterarSenhaUseCase $alterarSenha,
    ) {}

    #[OA\Put(
        path: '/api/me/senha',
        summary: 'Alterar a senha do usuário autenticado',
        description: 'Também conclui a troca obrigatória de senha do primeiro acesso. As demais sessões do usuário são encerradas.',
        security: [['sanctum' => []]],
        tags: ['Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AlterarSenhaRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Senha alterada com sucesso', content: new OA\JsonContent(ref: '#/components/schemas/UserResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function updateSenha(AlterarSenhaRequest $request)
    {
        $token = $request->user()->currentAccessToken();

        $user = $this->alterarSenha->execute(
            $request->user(),
            $request->validated('password'),
            $token instanceof PersonalAccessToken ? (int) $token->getKey() : null,
        );

        return (new UserResource($user))->additional([
            'message' => 'Senha alterada com sucesso.',
        ]);
    }
}
