<?php

namespace App\Modules\Pessoas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pessoas\Http\Requests\MedidaAluno\StoreMedidaAlunoRequest;
use App\Modules\Pessoas\Http\Requests\MedidaAluno\UpdateMedidaAlunoRequest;
use App\Modules\Pessoas\Models\MedidaAluno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Pessoas",
    description: "API Endpoints for Medidas Alunos"
)]
class MedidaAlunoController extends Controller
{
    #[OA\Get(
        path: "/api/medidas-alunos",
        summary: "Listar medidas de alunos",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de medidas recuperada com sucesso",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/MedidaAluno")
                )
            )
        ]
    )]
    public function index()
    {
        $medidas = MedidaAluno::query()->visivelPara(request()->user())->get();
        return response()->json($medidas);
    }

    #[OA\Post(
        path: "/api/medidas-alunos",
        summary: "Criar nova medida de aluno",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreMedidaAlunoRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Medida criada com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/MedidaAluno")
            ),
            new OA\Response(
                response: 422,
                description: "Erro de validação"
            )
        ]
    )]
    public function store(StoreMedidaAlunoRequest $request)
    {
        $medida = MedidaAluno::create($request->validated());
        return response()->json($medida, 201);
    }

    #[OA\Get(
        path: "/api/medidas-alunos/{id}",
        summary: "Exibir uma medida específica",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID da medida",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Detalhes da medida",
                content: new OA\JsonContent(ref: "#/components/schemas/MedidaAluno")
            ),
            new OA\Response(
                response: 404,
                description: "Medida não encontrada"
            )
        ]
    )]
    public function show($id)
    {
        $medida = MedidaAluno::findOrFail($id);

        Gate::authorize('view', $medida);

        return response()->json($medida);
    }

    #[OA\Put(
        path: "/api/medidas-alunos/{id}",
        summary: "Atualizar uma medida existente",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID da medida",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateMedidaAlunoRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Medida atualizada com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/MedidaAluno")
            ),
            new OA\Response(
                response: 404,
                description: "Medida não encontrada"
            ),
            new OA\Response(
                response: 422,
                description: "Erro de validação"
            )
        ]
    )]
    public function update(UpdateMedidaAlunoRequest $request, $id)
    {
        $medida = MedidaAluno::findOrFail($id);
        $medida->update($request->validated());
        return response()->json($medida);
    }

    #[OA\Delete(
        path: "/api/medidas-alunos/{id}",
        summary: "Remover uma medida",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID da medida",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Medida removida com sucesso"
            ),
            new OA\Response(
                response: 404,
                description: "Medida não encontrada"
            )
        ]
    )]
    public function destroy($id)
    {
        $medida = MedidaAluno::findOrFail($id);
        $medida->delete();
        return response()->json(null, 204);
    }

    #[OA\Get(
        path: "/api/alunos/{id_aluno}/medidas",
        summary: "Listar medidas de um aluno específico",
        security: [["sanctum" => []]],
        tags: ["Pessoas"],
        parameters: [
            new OA\Parameter(
                name: "id_aluno",
                in: "path",
                description: "ID do aluno",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de medidas do aluno",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/MedidaAluno")
                )
            )
        ]
    )]
    public function getByAluno($id_aluno)
    {
        Gate::authorize('viewAnyDoAluno', [MedidaAluno::class, (int) $id_aluno]);

        $medidas = MedidaAluno::where('id_aluno', $id_aluno)->get();
        return response()->json($medidas);
    }
}
