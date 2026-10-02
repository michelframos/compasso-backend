<?php

namespace App\Modules\Comercial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comercial\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class PublicLeadController extends Controller
{
    #[OA\Post(
        path: "/api/leads/public/{slug}",
        summary: "Captura um lead de forma pública (landing pages por escola)",
        tags: ["Comercial"],
        parameters: [
            new OA\Parameter(name: "slug", in: "path", required: true, schema: new OA\Schema(type: "string")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["nome"],
                properties: [
                    new OA\Property(property: "nome", type: "string", maxLength: 100),
                    new OA\Property(property: "email", type: "string", format: "email", maxLength: 100, nullable: true),
                    new OA\Property(property: "telefone", type: "string", maxLength: 20, nullable: true),
                    new OA\Property(property: "observacoes", type: "string", nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Lead capturado com sucesso"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:100',
            'email' => 'nullable|email|max:100',
            'telefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $lead = Lead::create($request->only(['nome', 'email', 'telefone', 'observacoes']));

        return response()->json([
            'message' => 'Lead capturado com sucesso!',
            'data' => $lead
        ], 201);
    }
}
