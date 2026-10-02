<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Http\Requests\User\StoreUserRequest;
use App\Modules\Core\Http\Requests\User\UpdateUserRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\UseCases\User\CreateUserUseCase;
use App\Modules\Core\UseCases\User\DeleteUserUseCase;
use App\Modules\Core\UseCases\User\UpdateUserUseCase;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly CreateUserUseCase $createUser,
        private readonly UpdateUserUseCase $updateUser,
        private readonly DeleteUserUseCase $deleteUser,
    ) {}

    #[OA\Get(path: '/api/users', summary: 'Listar usuários', security: [['sanctum' => []]], tags: ['Core'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request)
    {
        $roles = [];
        if ($request->filled('roles')) {
            $roles = is_array($request->roles) ? $request->roles : explode(',', $request->roles);
        }

        $users = $this->users->paginate(
            search: $request->get('search'),
            roles: $roles,
            perPage: 15,
        );

        return UserResource::collection($users);
    }

    #[OA\Post(path: '/api/users', summary: 'Criar novo usuário', security: [['sanctum' => []]], tags: ['Core'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function store(StoreUserRequest $request)
    {
        $user = $this->createUser->execute($request->validated());

        return new UserResource($user);
    }

    #[OA\Get(path: '/api/users/{id}', summary: 'Exibir usuário específico', security: [['sanctum' => []]], tags: ['Core'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show($id)
    {
        return new UserResource($this->users->findById($id));
    }

    #[OA\Put(path: '/api/users/{id}', summary: 'Atualizar usuário', security: [['sanctum' => []]], tags: ['Core'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function update(UpdateUserRequest $request, $id)
    {
        $user = $this->users->findById($id);
        $updated = $this->updateUser->execute($user, $request->validated());

        return new UserResource($updated);
    }

    #[OA\Delete(path: '/api/users/{id}', summary: 'Excluir usuário', security: [['sanctum' => []]], tags: ['Core'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function destroy($id)
    {
        $this->deleteUser->execute($id);

        return response()->json(null, 204);
    }
}
