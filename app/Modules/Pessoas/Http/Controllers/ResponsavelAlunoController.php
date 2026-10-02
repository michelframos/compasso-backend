<?php

namespace App\Modules\Pessoas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Pessoas\Models\Aluno;
use App\Modules\Pessoas\Models\Responsavel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Pessoas",
    description: "Gerenciamento de responsáveis dos alunos"
)]
class ResponsavelAlunoController extends Controller
{
    #[OA\Get(
        path: "/api/alunos/{aluno}/responsaveis",
        summary: "Listar responsáveis de um aluno",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(
                name: "aluno",
                in: "path",
                required: true,
                description: "ID do aluno",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de responsáveis recuperada com sucesso",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/Responsavel")
                )
            ),
            new OA\Response(response: 404, description: "Aluno não encontrado")
        ]
    )]
    public function index(Aluno $aluno)
    {
        return response()->json($aluno->responsaveis);
    }

    #[OA\Post(
        path: "/api/alunos/{aluno}/responsaveis",
        summary: "Vincular um responsável a um aluno",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(
                name: "aluno",
                in: "path",
                required: true,
                description: "ID do aluno",
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["id_responsavel"],
                properties: [
                    new OA\Property(property: "id_responsavel", type: "integer", description: "ID do responsável"),
                    new OA\Property(property: "parentesco", type: "string", description: "Grau de parentesco"),
                    new OA\Property(property: "observacoes", type: "string", description: "Observações adicionais")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Responsável vinculado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Responsável vinculado com sucesso"),
                        new OA\Property(property: "data", ref: "#/components/schemas/Responsavel")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Aluno ou Responsável não encontrado"),
            new OA\Response(response: 422, description: "Dados inválidos")
        ]
    )]
    public function store(Request $request, Aluno $aluno)
    {
        $validated = $request->validate([
            'id_responsavel' => ['required', InstituicaoContext::existsRule('responsaveis')],
            'parentesco' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string',
        ]);

        $aluno->responsaveis()->attach($validated['id_responsavel'], InstituicaoContext::pivotAttributes([
            'parentesco' => $validated['parentesco'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
        ]));

        // Carregar o responsável recém-vinculado com os dados da tabela pivô
        $responsavel = $aluno->responsaveis()->where('responsaveis.id', $validated['id_responsavel'])->first();

        return response()->json([
            'message' => 'Responsável vinculado com sucesso',
            'data' => $responsavel
        ], 201);
    }

    #[OA\Get(
        path: "/api/responsaveis/{responsavel}/alunos",
        summary: "Listar alunos de um responsável",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(
                name: "responsavel",
                in: "path",
                required: true,
                description: "ID do responsável",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de alunos recuperada com sucesso",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/Aluno")
                )
            ),
            new OA\Response(response: 404, description: "Responsável não encontrado")
        ]
    )]
    public function alunos(Responsavel $responsavel)
    {
        return response()->json($responsavel->alunos);
    }

    #[OA\Delete(
        path: "/api/alunos/{aluno}/responsaveis/{responsavel}",
        summary: "Desvincular um responsável de um aluno (Soft Delete)",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(
                name: "aluno",
                in: "path",
                required: true,
                description: "ID do aluno",
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "responsavel",
                in: "path",
                required: true,
                description: "ID do responsável",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Responsável desvinculado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Responsável desvinculado com sucesso")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Aluno ou Responsável não encontrado")
        ]
    )]
    public function destroy(Aluno $aluno, Responsavel $responsavel)
    {
        // Soft Delete na tabela pivot
        $aluno->responsaveis()->updateExistingPivot($responsavel->id, ['deleted_at' => now()]);

        return response()->json(['message' => 'Responsável desvinculado com sucesso']);
    }
}
