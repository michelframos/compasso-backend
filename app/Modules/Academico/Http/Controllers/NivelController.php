<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use App\Modules\Academico\Http\Requests\Nivel\StoreNivelRequest;
use App\Modules\Academico\Http\Requests\Nivel\UpdateNivelRequest;
use App\Modules\Academico\Http\Resources\NivelResource;
use App\Modules\Academico\Models\Nivel;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NivelController extends Controller
{
    use ResolvesSorting;

    #[OA\Get(
        path: "/api/niveis",
        summary: "Listar níveis",
        security: [["sanctum" => []]],
        tags: ["Academico"],
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
                            items: new OA\Items(ref: "#/components/schemas/NivelResource")
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
        $search     = request('search');
        $curso_id   = request('curso_id');

        $query = Nivel::with('curso');

        if ($search) {
            $query->where('nome', 'like', "%{$search}%");
        }

        if ($curso_id) {
            $query->where('curso_id', $curso_id);
        }

        $niveis = $this->applySorting($query, ['id', 'nome', 'observacoes'], 'nome')->paginate(15);
        return NivelResource::collection($niveis);
    }

    #[OA\Post(
        path: "/api/niveis",
        summary: "Criar novo nível",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreNivelRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Nível criado",
                content: new OA\JsonContent(ref: "#/components/schemas/NivelResource")
            ),
            new OA\Response(
                response: 422,
                description: "Erro de validação"
            )
        ]
    )]
    public function store(StoreNivelRequest $request)
    {
        $nivel = Nivel::create($request->validated());
        return new NivelResource($nivel);
    }

    #[OA\Get(
        path: "/api/niveis/{id}",
        summary: "Exibir nível específico",
        security: [["sanctum" => []]],
        tags: ["Academico"],
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
                content: new OA\JsonContent(ref: "#/components/schemas/NivelResource")
            ),
            new OA\Response(
                response: 404,
                description: "Nível não encontrado"
            )
        ]
    )]
    public function show($id)
    {
        $nivel = Nivel::findOrFail($id);
        return new NivelResource($nivel);
    }

    #[OA\Put(
        path: "/api/niveis/{id}",
        summary: "Atualizar nível",
        security: [["sanctum" => []]],
        tags: ["Academico"],
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
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateNivelRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Nível atualizado",
                content: new OA\JsonContent(ref: "#/components/schemas/NivelResource")
            ),
            new OA\Response(
                response: 404,
                description: "Nível não encontrado"
            ),
            new OA\Response(
                response: 422,
                description: "Erro de validação"
            )
        ]
    )]
    public function update(UpdateNivelRequest $request, $id)
    {
        $nivel = Nivel::findOrFail($id);
        $nivel->update($request->validated());
        return new NivelResource($nivel);
    }

    #[OA\Delete(
        path: "/api/niveis/{id}",
        summary: "Excluir nível",
        security: [["sanctum" => []]],
        tags: ["Academico"],
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
                description: "Nível excluído"
            ),
            new OA\Response(
                response: 404,
                description: "Nível não encontrado"
            )
        ]
    )]
    public function destroy($id)
    {
        $nivel = Nivel::findOrFail($id);
        $nivel->delete();
        return response()->json(null, 204);
    }
}
