<?php

namespace App\Modules\Espetaculos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Http\Requests\Apresentacao\StoreApresentacaoRequest;
use App\Modules\Espetaculos\Http\Requests\Apresentacao\UpdateApresentacaoRequest;
use App\Modules\Espetaculos\Http\Resources\ApresentacaoResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class ApresentacaoController extends Controller
{
    #[OA\Get(
        path: "/api/apresentacoes",
        summary: "Lista todas as apresentações",
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
                            items: new OA\Items(ref: "#/components/schemas/ApresentacaoResource")
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
        $apresentacoes = Apresentacao::query()
            ->visivelPara($request->user())
            ->with(['espetaculo', 'turma', 'alunos'])
            ->latest()
            ->paginate(15);
        return ApresentacaoResource::collection($apresentacoes);
    }

    #[OA\Post(
        path: "/api/apresentacoes",
        summary: "Cria uma nova apresentação",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreApresentacaoRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Apresentação criada com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/ApresentacaoResource")
            ),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function store(StoreApresentacaoRequest $request)
    {
        $apresentacao = Apresentacao::create($request->validated());
        $apresentacao->load(['espetaculo', 'turma']);
        return new ApresentacaoResource($apresentacao);
    }

    #[OA\Get(
        path: "/api/apresentacoes/{id}",
        summary: "Exibe os detalhes de uma apresentação",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operação bem-sucedida",
                content: new OA\JsonContent(ref: "#/components/schemas/ApresentacaoResource")
            ),
            new OA\Response(response: 403, description: "Apresentação sem turma ou alunos do professor"),
            new OA\Response(response: 404, description: "Apresentação não encontrada")
        ]
    )]
    public function show(Apresentacao $apresentacao)
    {
        Gate::authorize('view', $apresentacao);

        $apresentacao->load(['espetaculo', 'turma', 'alunos']);
        return new ApresentacaoResource($apresentacao);
    }

    #[OA\Put(
        path: "/api/apresentacoes/{id}",
        summary: "Atualiza uma apresentação",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateApresentacaoRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Apresentação atualizada",
                content: new OA\JsonContent(ref: "#/components/schemas/ApresentacaoResource")
            ),
            new OA\Response(response: 404, description: "Apresentação não encontrada"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function update(UpdateApresentacaoRequest $request, Apresentacao $apresentacao)
    {
        $apresentacao->update($request->validated());
        $apresentacao->load(['espetaculo', 'turma']);
        return new ApresentacaoResource($apresentacao);
    }

    #[OA\Delete(
        path: "/api/apresentacoes/{id}",
        summary: "Exclui uma apresentação logicamente (Soft Delete)",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Apresentação excluída"),
            new OA\Response(response: 404, description: "Apresentação não encontrada")
        ]
    )]
    public function destroy(Apresentacao $apresentacao)
    {
        $apresentacao->delete();
        return response()->json(null, 204);
    }
}
