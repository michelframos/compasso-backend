<?php

namespace App\Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use App\Modules\Financeiro\Http\Requests\Contrato\StoreContratoRequest;
use App\Modules\Financeiro\Http\Requests\Contrato\UpdateContratoRequest;
use App\Modules\Financeiro\Http\Resources\ContratoResource;
use App\Modules\Financeiro\Models\Contrato;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ContratoController extends Controller
{
    use ResolvesSorting;

    #[OA\Get(
        path: "/api/contratos",
        summary: "Listar contratos",
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
                            items: new OA\Items(ref: "#/components/schemas/ContratoResource")
                        )
                    ]
                )
            )
        ]
    )]
    public function index()
    {
        $search = request('search');

        $query = Contrato::query();

        if ($search) {
            $query->where('nome', 'like', "%{$search}%");
        }

        $contratos = $this->applySorting($query, ['id', 'nome', 'created_at'], 'nome')->paginate(15);
        return ContratoResource::collection($contratos);
    }

    #[OA\Post(
        path: "/api/contratos",
        summary: "Criar novo contrato",
        security: [["sanctum" => []]],
        tags: ["Financeiro"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreContratoRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Contrato criado",
                content: new OA\JsonContent(ref: "#/components/schemas/ContratoResource")
            )
        ]
    )]
    public function store(StoreContratoRequest $request)
    {
        $contrato = Contrato::create($request->validated());
        return new ContratoResource($contrato);
    }

    #[OA\Get(
        path: "/api/contratos/{id}",
        summary: "Exibir contrato específico",
        security: [["sanctum" => []]],
        tags: ["Financeiro"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operação bem-sucedida",
                content: new OA\JsonContent(ref: "#/components/schemas/ContratoResource")
            )
        ]
    )]
    public function show($id)
    {
        $contrato = Contrato::findOrFail($id);
        return new ContratoResource($contrato);
    }

    #[OA\Put(
        path: "/api/contratos/{id}",
        summary: "Atualizar contrato",
        security: [["sanctum" => []]],
        tags: ["Financeiro"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateContratoRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Contrato atualizado",
                content: new OA\JsonContent(ref: "#/components/schemas/ContratoResource")
            )
        ]
    )]
    public function update(UpdateContratoRequest $request, $id)
    {
        $contrato = Contrato::findOrFail($id);
        $contrato->update($request->validated());
        return new ContratoResource($contrato);
    }

    #[OA\Delete(
        path: "/api/contratos/{id}",
        summary: "Excluir contrato",
        security: [["sanctum" => []]],
        tags: ["Financeiro"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Contrato excluído")
        ]
    )]
    public function destroy($id)
    {
        $contrato = Contrato::findOrFail($id);
        $contrato->delete();
        return response()->json(null, 204);
    }
}
