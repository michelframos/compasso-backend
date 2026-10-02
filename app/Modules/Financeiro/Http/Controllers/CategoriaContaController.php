<?php

namespace App\Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Financeiro\Http\Requests\CategoriaConta\StoreCategoriaContaRequest;
use App\Modules\Financeiro\Http\Requests\CategoriaConta\UpdateCategoriaContaRequest;
use App\Modules\Financeiro\Http\Resources\CategoriaContaResource;
use App\Modules\Financeiro\Models\CategoriaConta;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CategoriaContaController extends Controller
{
    #[OA\Get(
        path: "/api/categorias-contas",
        summary: "Listar categorias de contas",
        security: [["sanctum" => []]],
        tags: ["Financeiro"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operação bem-sucedida",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/CategoriaContaResource")
                        ),
                        new OA\Property(
                            property: "links",
                            type: "object"
                        ),
                        new OA\Property(
                            property: "meta",
                            type: "object"
                        )
                    ]
                )
            )
        ]
    )]
    public function index()
    {
        $categorias = CategoriaConta::paginate(15);
        return CategoriaContaResource::collection($categorias);
    }

    #[OA\Post(
        path: "/api/categorias-contas",
        summary: "Criar nova categoria de conta",
        security: [["sanctum" => []]],
        tags: ["Financeiro"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreCategoriaContaRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Categoria criada",
                content: new OA\JsonContent(ref: "#/components/schemas/CategoriaContaResource")
            ),
            new OA\Response(
                response: 422,
                description: "Erro de validação"
            )
        ]
    )]
    public function store(StoreCategoriaContaRequest $request)
    {
        $categoria = CategoriaConta::create($request->validated());
        return new CategoriaContaResource($categoria);
    }

    #[OA\Get(
        path: "/api/categorias-contas/{id}",
        summary: "Exibir categoria específica",
        security: [["sanctum" => []]],
        tags: ["Financeiro"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operação bem-sucedida",
                content: new OA\JsonContent(ref: "#/components/schemas/CategoriaContaResource")
            ),
            new OA\Response(
                response: 404,
                description: "Categoria não encontrada"
            )
        ]
    )]
    public function show($id)
    {
        $categoria = CategoriaConta::findOrFail($id);
        return new CategoriaContaResource($categoria);
    }

    #[OA\Put(
        path: "/api/categorias-contas/{id}",
        summary: "Atualizar categoria",
        security: [["sanctum" => []]],
        tags: ["Financeiro"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateCategoriaContaRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Categoria atualizada",
                content: new OA\JsonContent(ref: "#/components/schemas/CategoriaContaResource")
            ),
            new OA\Response(
                response: 404,
                description: "Categoria não encontrada"
            ),
            new OA\Response(
                response: 422,
                description: "Erro de validação"
            )
        ]
    )]
    public function update(UpdateCategoriaContaRequest $request, $id)
    {
        $categoria = CategoriaConta::findOrFail($id);
        $categoria->update($request->validated());
        return new CategoriaContaResource($categoria);
    }

    #[OA\Delete(
        path: "/api/categorias-contas/{id}",
        summary: "Excluir categoria",
        security: [["sanctum" => []]],
        tags: ["Financeiro"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Categoria excluída"
            ),
            new OA\Response(
                response: 404,
                description: "Categoria não encontrada"
            )
        ]
    )]
    public function destroy($id)
    {
        $categoria = CategoriaConta::findOrFail($id);

        // Em um sistema real, poderíamos verificar se há contas vinculadas antes de excluir!
        // Como o BD usa restrição (se houver foreign key sem cascade, vai disparar exception)

        $categoria->delete();
        return response()->json(null, 204);
    }
}
