<?php

namespace App\Modules\Pessoas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pessoas\Http\Requests\Responsavel\StoreResponsavelRequest;
use App\Modules\Pessoas\Http\Requests\Responsavel\UpdateResponsavelRequest;
use App\Modules\Pessoas\Models\Responsavel;
use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Pessoas\UseCases\Responsavel\CreateResponsavelUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Pessoas",
    description: "Gerenciamento de Responsáveis"
)]
class ResponsavelController extends Controller
{
    public function __construct(
        private readonly CreateResponsavelUseCase $createResponsavel,
        private readonly UserRepositoryInterface $users,
    ) {}

    #[OA\Get(
        path: "/api/responsaveis",
        summary: "Listar responsáveis",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de responsáveis",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/Responsavel")
                )
            )
        ]
    )]
    public function index()
    {
        $responsaveis = Responsavel::with('usuario')
            ->whereHas('usuario', function ($query) {
                $query->where('role', 'responsavel');
            })
            ->get();
        return response()->json($responsaveis);
    }

    #[OA\Post(
        path: "/api/responsaveis",
        summary: "Criar novo responsável",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreResponsavelRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Responsável criado com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/Responsavel")
            ),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function store(StoreResponsavelRequest $request)
    {
        $responsavel = $this->createResponsavel->execute($request->validated());

        return response()->json($responsavel, 201);
    }

    #[OA\Get(
        path: "/api/responsaveis/{id}",
        summary: "Exibir responsável específico",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Detalhes do responsável",
                content: new OA\JsonContent(ref: "#/components/schemas/Responsavel")
            ),
            new OA\Response(response: 404, description: "Responsável não encontrado")
        ]
    )]
    public function show(Responsavel $responsavel)
    {
        Gate::authorize('view', $responsavel);

        // Ensure relation is loaded
        $responsavel->load('usuario');

        // Check if user exists before accessing properties
        if (!$responsavel->usuario || $responsavel->usuario->role !== 'responsavel') {
            return response()->json(['message' => 'Responsável não encontrado'], 404);
        }

        return response()->json($responsavel);
    }

    #[OA\Put(
        path: "/api/responsaveis/{id}",
        summary: "Atualizar responsável",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateResponsavelRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Responsável atualizado",
                content: new OA\JsonContent(ref: "#/components/schemas/Responsavel")
            ),
            new OA\Response(response: 404, description: "Responsável não encontrado")
        ]
    )]
    public function update(UpdateResponsavelRequest $request, Responsavel $responsavel)
    {
        Gate::authorize('update', $responsavel);

        return DB::transaction(function () use ($request, $responsavel) {
            $userData = $request->only(['nome', 'email', 'cpf', 'telefone', 'whatsapp']);
            if ($request->filled('password')) {
                $userData['password'] = $request->password;
            }

            if ($responsavel->usuario) {
                $this->users->update($responsavel->usuario, $userData);
            }

            $responsavel->update($request->only(['observacoes']));

            return response()->json($responsavel->load('usuario'));
        });
    }

    #[OA\Delete(
        path: "/api/responsaveis/{id}",
        summary: "Excluir responsável",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Responsável excluído"),
            new OA\Response(response: 403, description: "Não autorizado: Responsável vinculado a alunos"),
            new OA\Response(response: 404, description: "Responsável não encontrado")
        ]
    )]
    public function destroy(Responsavel $responsavel)
    {
        if ($responsavel->alunos()->exists()) {
            return response()->json(['message' => 'Não é possível excluir o responsável pois ele possui alunos vinculados.'], 403);
        }

        return DB::transaction(function () use ($responsavel) {
            $user = $responsavel->usuario;
            $responsavel->delete();
            if ($user) {
                $this->users->delete($user);
            }

            return response()->json(null, 204);
        });
    }
}
