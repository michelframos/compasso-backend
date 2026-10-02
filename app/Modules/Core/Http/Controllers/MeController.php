<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\User\AlterarSenhaRequest;
use App\Modules\Core\Http\Requests\User\AtualizarFotoPerfilRequest;
use App\Modules\Core\Http\Requests\User\UpdatePerfilRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\UseCases\User\AlterarSenhaUseCase;
use App\Modules\Core\UseCases\User\AtualizarFotoPerfilUseCase;
use App\Modules\Core\UseCases\User\AtualizarPerfilUseCase;
use App\Modules\Core\UseCases\User\RemoverFotoPerfilUseCase;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;

class MeController extends Controller
{
    public function __construct(
        private readonly AlterarSenhaUseCase $alterarSenha,
        private readonly AtualizarPerfilUseCase $atualizarPerfil,
        private readonly AtualizarFotoPerfilUseCase $atualizarFoto,
        private readonly RemoverFotoPerfilUseCase $removerFoto,
    ) {}

    #[OA\Get(
        path: '/api/me/perfil',
        summary: 'Dados do perfil do usuário autenticado',
        security: [['sanctum' => []]],
        tags: ['Core'],
        responses: [
            new OA\Response(response: 200, description: 'Perfil do usuário', content: new OA\JsonContent(ref: '#/components/schemas/UserResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Troca de senha obrigatória pendente'),
        ]
    )]
    public function showPerfil(Request $request)
    {
        return new UserResource($request->user());
    }

    #[OA\Put(
        path: '/api/me/perfil',
        summary: 'Atualizar os dados pessoais do usuário autenticado',
        description: 'Disponível para todos os papéis. E-mail, CPF e papel não são alterados por aqui.',
        security: [['sanctum' => []]],
        tags: ['Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdatePerfilRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Perfil atualizado', content: new OA\JsonContent(ref: '#/components/schemas/UserResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Troca de senha obrigatória pendente'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function updatePerfil(UpdatePerfilRequest $request)
    {
        $user = $this->atualizarPerfil->execute($request->user(), $request->validated());

        return (new UserResource($user))->additional([
            'message' => 'Perfil atualizado com sucesso.',
        ]);
    }

    #[OA\Post(
        path: '/api/me/perfil/foto',
        summary: 'Enviar ou substituir a foto de perfil do usuário autenticado',
        security: [['sanctum' => []]],
        tags: ['Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/AtualizarFotoPerfilRequest')
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Foto atualizada', content: new OA\JsonContent(ref: '#/components/schemas/UserResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function updateFoto(AtualizarFotoPerfilRequest $request)
    {
        $user = $this->atualizarFoto->execute($request->user(), $request->file('foto'));

        return (new UserResource($user))->additional([
            'message' => 'Foto atualizada com sucesso.',
        ]);
    }

    #[OA\Delete(
        path: '/api/me/perfil/foto',
        summary: 'Remover a foto de perfil do usuário autenticado',
        security: [['sanctum' => []]],
        tags: ['Core'],
        responses: [
            new OA\Response(response: 200, description: 'Foto removida', content: new OA\JsonContent(ref: '#/components/schemas/UserResource')),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function destroyFoto(Request $request)
    {
        $user = $this->removerFoto->execute($request->user());

        return (new UserResource($user))->additional([
            'message' => 'Foto removida.',
        ]);
    }

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
