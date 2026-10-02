<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\InstituicaoRepositoryInterface;
use App\Modules\Core\Http\Requests\Platform\ImpersonatePlatformInstituicaoUserRequest;
use App\Modules\Core\Http\Requests\Platform\StorePlatformInstituicaoRequest;
use App\Modules\Core\Http\Requests\Platform\UpdatePlatformInstituicaoRequest;
use App\Modules\Core\Http\Resources\InstituicaoResource;
use App\Modules\Core\Http\Resources\PlatformInstituicaoResource;
use App\Modules\Core\Http\Resources\PlatformInstituicaoUsuarioResource;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\UseCases\Platform\CreatePlatformInstituicaoUseCase;
use App\Modules\Core\UseCases\Platform\DeletePlatformInstituicaoUseCase;
use App\Modules\Core\UseCases\Platform\ImpersonateInstituicaoUserUseCase;
use App\Modules\Core\UseCases\Platform\ListPlatformInstituicaoUsuariosUseCase;
use App\Modules\Core\UseCases\Platform\UpdatePlatformInstituicaoUseCase;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PlatformInstituicaoController extends Controller
{
    public function __construct(
        private readonly InstituicaoRepositoryInterface $instituicoes,
        private readonly CreatePlatformInstituicaoUseCase $createInstituicao,
        private readonly UpdatePlatformInstituicaoUseCase $updateInstituicao,
        private readonly DeletePlatformInstituicaoUseCase $deleteInstituicao,
        private readonly ListPlatformInstituicaoUsuariosUseCase $listUsuarios,
        private readonly ImpersonateInstituicaoUserUseCase $impersonateUser,
    ) {}

    #[OA\Get(path: '/api/platform/instituicoes', summary: 'Listar instituições (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request)
    {
        $instituicoes = $this->instituicoes->paginate(
            search: $request->get('search'),
            status: $request->get('status'),
            withTrashed: $request->boolean('with_trashed'),
            perPage: (int) $request->get('per_page', 15),
        );

        return PlatformInstituicaoResource::collection($instituicoes);
    }

    #[OA\Post(
        path: '/api/platform/instituicoes',
        summary: 'Criar instituição (escola)',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StorePlatformInstituicaoRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/PlatformInstituicaoResource')),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(StorePlatformInstituicaoRequest $request)
    {
        $instituicao = $this->createInstituicao->execute($request->validated());

        return (new PlatformInstituicaoResource($instituicao))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(path: '/api/platform/instituicoes/{id}', summary: 'Exibir instituição (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(int $instituicao, Request $request)
    {
        $model = $this->instituicoes->findById(
            $instituicao,
            withTrashed: $request->boolean('with_trashed'),
        );

        return new PlatformInstituicaoResource($model);
    }

    #[OA\Put(path: '/api/platform/instituicoes/{id}', summary: 'Atualizar instituição (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function update(UpdatePlatformInstituicaoRequest $request, int $instituicao)
    {
        $model = $this->instituicoes->findById($instituicao);
        $updated = $this->updateInstituicao->execute($model, $request->validated());

        return new PlatformInstituicaoResource($updated);
    }

    #[OA\Delete(path: '/api/platform/instituicoes/{id}', summary: 'Excluir instituição (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 204, description: 'No Content')]
    )]
    public function destroy(int $instituicao)
    {
        $model = $this->instituicoes->findById($instituicao);
        $this->deleteInstituicao->execute($model);

        return response()->json(null, 204);
    }

    #[OA\Get(path: '/api/platform/instituicoes/{id}/usuarios', summary: 'Listar usuários da instituição (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function usuarios(int $instituicao, Request $request)
    {
        $usuarios = $this->listUsuarios->execute(
            $instituicao,
            search: $request->get('search'),
            perPage: (int) $request->get('per_page', 15),
        );

        return PlatformInstituicaoUsuarioResource::collection($usuarios);
    }

    #[OA\Post(
        path: '/api/platform/instituicoes/{id}/impersonate',
        summary: 'Entrar como usuário da instituição',
        description: 'Emite token do usuário alvo com impersonator_user_id. Permite acessar escolas suspensas ou com trial expirado.',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ImpersonatePlatformUserRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(ref: '#/components/schemas/ImpersonatePlatformUserResponse')
            ),
            new OA\Response(response: 422, description: 'Usuário inválido ou é super-admin'),
        ]
    )]
    public function impersonate(ImpersonatePlatformInstituicaoUserRequest $request, int $instituicao)
    {
        $result = $this->impersonateUser->execute(
            $request->user(),
            $instituicao,
            $request->validated('user_id'),
            $request,
        );

        return response()->json([
            'token' => $result['token'],
            'tenant' => new InstituicaoResource($result['tenant']),
            'user' => new UserResource($result['user']),
            'impersonation' => $result['impersonation'],
        ]);
    }
}
