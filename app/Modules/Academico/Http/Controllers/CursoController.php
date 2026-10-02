<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use App\Modules\Academico\Http\Requests\Curso\StoreCursoRequest;
use App\Modules\Academico\Http\Requests\Curso\UpdateCursoRequest;
use App\Modules\Academico\Http\Resources\CursoResource;
use App\Modules\Academico\Models\Curso;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CursoController extends Controller
{
    use ResolvesSorting;

    #[OA\Get(
        path: "/api/cursos",
        summary: "Listar cursos",
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
                            items: new OA\Items(ref: "#/components/schemas/CursoResource")
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
        $search = request('search');

        $query = Curso::query();

        if ($search) {
            $query->where('nome', 'like', "%{$search}%");
        }

        $cursos = $this->applySorting($query, ['id', 'nome', 'descricao'], 'nome')->paginate(15);
        return CursoResource::collection($cursos);
    }

    #[OA\Post(
        path: "/api/cursos",
        summary: "Criar novo curso",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreCursoRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Curso criado",
                content: new OA\JsonContent(ref: "#/components/schemas/CursoResource")
            ),
            new OA\Response(
                response: 422,
                description: "Erro de validação"
            )
        ]
    )]
    public function store(StoreCursoRequest $request)
    {
        $curso = Curso::create($request->validated());
        return new CursoResource($curso);
    }

    #[OA\Get(
        path: "/api/cursos/{id}",
        summary: "Exibir curso específico",
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
                content: new OA\JsonContent(ref: "#/components/schemas/CursoResource")
            ),
            new OA\Response(
                response: 404,
                description: "Curso não encontrado"
            )
        ]
    )]
    public function show($id)
    {
        $curso = Curso::findOrFail($id);
        return new CursoResource($curso);
    }

    #[OA\Put(
        path: "/api/cursos/{id}",
        summary: "Atualizar curso",
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
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateCursoRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Curso atualizado",
                content: new OA\JsonContent(ref: "#/components/schemas/CursoResource")
            ),
            new OA\Response(
                response: 404,
                description: "Curso não encontrado"
            ),
            new OA\Response(
                response: 422,
                description: "Erro de validação"
            )
        ]
    )]
    public function update(UpdateCursoRequest $request, $id)
    {
        $curso = Curso::findOrFail($id);
        $curso->update($request->validated());
        return new CursoResource($curso);
    }

    #[OA\Delete(
        path: "/api/cursos/{id}",
        summary: "Excluir curso",
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
                description: "Curso excluído"
            ),
            new OA\Response(
                response: 404,
                description: "Curso não encontrado"
            )
        ]
    )]
    public function destroy($id)
    {
        $curso = Curso::findOrFail($id);
        $curso->delete();
        return response()->json(null, 204);
    }
}
