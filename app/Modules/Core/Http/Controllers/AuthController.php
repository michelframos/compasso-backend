<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Auth\LoginRequest;
use App\Modules\Core\Http\Requests\Auth\PlatformLoginRequest;
use App\Modules\Core\Http\Requests\Auth\RegisterRequest;
use App\Modules\Core\Http\Resources\InstituicaoResource;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\UseCases\Auth\LoginPlatformUserUseCase;
use App\Modules\Core\UseCases\Auth\LoginUserUseCase;
use App\Modules\Core\UseCases\Auth\RegisterUserUseCase;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUserUseCase $registerUser,
        private readonly LoginUserUseCase $loginUser,
        private readonly LoginPlatformUserUseCase $loginPlatformUser,
    ) {}

    #[OA\Post(
        path: '/api/register',
        summary: 'Registrar novo usuário',
        tags: ['Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RegisterRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuário registrado com sucesso', content: new OA\JsonContent(ref: '#/components/schemas/UserResource')),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function register(RegisterRequest $request)
    {
        $result = $this->registerUser->execute($request->validated());

        return (new UserResource($result['user']))
            ->additional(['token' => $result['token']])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Login no painel da escola',
        tags: ['Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login realizado com sucesso'),
            new OA\Response(response: 401, description: 'Credenciais inválidas'),
            new OA\Response(response: 403, description: 'Sem acesso à instituição ou assinatura bloqueada'),
            new OA\Response(response: 404, description: 'Instituição não encontrada'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();
        $result = $this->loginUser->execute(
            $credentials['email'],
            $credentials['password'],
            $credentials['tenant_cnpj'] ?? null,
            $credentials['tenant_slug'] ?? null,
            $credentials['activation_code'] ?? null,
        );

        if ($result === null) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        if (isset($result['forbidden'])) {
            return response()->json(['message' => 'Acesso negado para esta instituição.'], 403);
        }

        if (isset($result['not_found'])) {
            return response()->json(['message' => 'Instituição não encontrada.'], 404);
        }

        if (isset($result['activation_required'])) {
            return response()->json([
                'message' => 'Informe o código de ativação enviado para o seu e-mail.',
                'code' => 'activation_required',
            ], 403);
        }

        if (isset($result['activation_invalid'])) {
            return response()->json([
                'message' => 'Código de ativação inválido ou expirado.',
                'errors' => ['activation_code' => ['Código de ativação inválido ou expirado.']],
            ], 422);
        }

        if (isset($result['subscription_blocked'])) {
            return response()->json($result['details'], 403);
        }

        return (new UserResource($result['user']))->additional([
            'token' => $result['token'],
            'tenant' => new InstituicaoResource($result['tenant']),
            'tenants' => InstituicaoResource::collection($result['tenants']),
        ]);
    }

    #[OA\Post(
        path: '/api/platform/login',
        summary: 'Login do super admin no painel da plataforma',
        tags: ['Core'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PlatformLoginRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login realizado com sucesso'),
            new OA\Response(response: 401, description: 'Credenciais inválidas'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function platformLogin(PlatformLoginRequest $request)
    {
        $credentials = $request->validated();
        $result = $this->loginPlatformUser->execute($credentials['email'], $credentials['password']);

        if ($result === null) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        return (new UserResource($result['user']))->additional([
            'token' => $result['token'],
            'tenants' => [],
        ]);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Logout de usuário',
        security: [['sanctum' => []]],
        tags: ['Core'],
        responses: [
            new OA\Response(response: 200, description: 'Logout realizado com sucesso'),
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Desconectado com sucesso']);
    }
}
