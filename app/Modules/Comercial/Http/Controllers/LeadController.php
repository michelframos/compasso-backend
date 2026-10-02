<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comercial\Models\Lead;
use App\Modules\Comercial\Http\Requests\Lead\StoreLeadRequest;
use App\Modules\Comercial\Http\Requests\Lead\UpdateLeadRequest;
use App\Modules\Comercial\Http\Resources\LeadResource;
use OpenApi\Attributes as OA;

class LeadController extends Controller
{
    #[OA\Get(
        path: "/api/leads",
        summary: "Lista todos os leads",
        security: [["sanctum" => []]],
        tags: ["Comercial"],
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
                            items: new OA\Items(ref: "#/components/schemas/LeadResource")
                        ),
                        new OA\Property(property: "links", type: "object"),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            )
        ]
    )]
    public function index()
    {
        $leads = Lead::latest()->paginate(15);
        return LeadResource::collection($leads);
    }

    #[OA\Post(
        path: "/api/leads",
        summary: "Cria um novo lead",
        security: [["sanctum" => []]],
        tags: ["Comercial"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreLeadRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Lead criado com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/LeadResource")
            ),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function store(StoreLeadRequest $request)
    {
        $lead = Lead::create($request->validated());
        return new LeadResource($lead);
    }

    #[OA\Get(
        path: "/api/leads/{id}",
        summary: "Exibe os detalhes de um lead",
        security: [["sanctum" => []]],
        tags: ["Comercial"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operação bem-sucedida",
                content: new OA\JsonContent(ref: "#/components/schemas/LeadResource")
            ),
            new OA\Response(response: 404, description: "Lead não encontrado")
        ]
    )]
    public function show(Lead $lead)
    {
        return new LeadResource($lead);
    }

    #[OA\Put(
        path: "/api/leads/{id}",
        summary: "Atualiza um lead",
        security: [["sanctum" => []]],
        tags: ["Comercial"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateLeadRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Lead atualizado",
                content: new OA\JsonContent(ref: "#/components/schemas/LeadResource")
            ),
            new OA\Response(response: 404, description: "Lead não encontrado"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function update(UpdateLeadRequest $request, Lead $lead)
    {
        $lead->update($request->validated());

        return new LeadResource($lead);
    }

    #[OA\Delete(
        path: "/api/leads/{id}",
        summary: "Exclui um lead",
        security: [["sanctum" => []]],
        tags: ["Comercial"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Lead excluído"),
            new OA\Response(response: 404, description: "Lead não encontrado")
        ]
    )]
    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->json(null, 204);
    }
}
