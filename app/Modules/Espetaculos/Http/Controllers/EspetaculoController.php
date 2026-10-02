<?php

namespace App\Modules\Espetaculos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Espetaculos\Models\Espetaculo;
use App\Modules\Espetaculos\Http\Requests\Espetaculo\StoreEspetaculoRequest;
use App\Modules\Espetaculos\Http\Requests\Espetaculo\UpdateEspetaculoRequest;
use App\Modules\Espetaculos\Http\Resources\EspetaculoResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class EspetaculoController extends Controller
{
    #[OA\Get(
        path: "/api/espetaculos",
        summary: "Lista todos os espetáculos",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
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
                            items: new OA\Items(ref: "#/components/schemas/EspetaculoResource")
                        ),
                        new OA\Property(property: "links", type: "object"),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $espetaculos = Espetaculo::query()->visivelPara($request->user())->latest()->paginate(15);
        return EspetaculoResource::collection($espetaculos);
    }

    #[OA\Post(
        path: "/api/espetaculos",
        summary: "Cria um novo espetáculo",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreEspetaculoRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Espetáculo criado com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/EspetaculoResource")
            ),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function store(StoreEspetaculoRequest $request)
    {
        $espetaculo = Espetaculo::create($request->validated());
        return new EspetaculoResource($espetaculo);
    }

    #[OA\Get(
        path: "/api/espetaculos/{id}",
        summary: "Exibe os detalhes de um espetáculo",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operação bem-sucedida",
                content: new OA\JsonContent(ref: "#/components/schemas/EspetaculoResource")
            ),
            new OA\Response(response: 403, description: "Professor sem turmas ou alunos no espetáculo"),
            new OA\Response(response: 404, description: "Espetáculo não encontrado")
        ]
    )]
    public function show(Espetaculo $espetaculo)
    {
        Gate::authorize('view', $espetaculo);

        return new EspetaculoResource($espetaculo);
    }

    #[OA\Put(
        path: "/api/espetaculos/{id}",
        summary: "Atualiza um espetáculo",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateEspetaculoRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Espetáculo atualizado",
                content: new OA\JsonContent(ref: "#/components/schemas/EspetaculoResource")
            ),
            new OA\Response(response: 404, description: "Espetáculo não encontrado"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function update(UpdateEspetaculoRequest $request, Espetaculo $espetaculo)
    {
        $espetaculo->update($request->validated());
        return new EspetaculoResource($espetaculo);
    }

    #[OA\Delete(
        path: "/api/espetaculos/{id}",
        summary: "Exclui um espetáculo logicamente (Soft Delete)",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Espetáculo excluído"),
            new OA\Response(response: 404, description: "Espetáculo não encontrado")
        ]
    )]
    public function destroy(Espetaculo $espetaculo)
    {
        $espetaculo->delete();
        return response()->json(null, 204);
    }
}
