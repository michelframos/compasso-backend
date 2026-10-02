<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Models\Turma;
use Illuminate\Http\Request;
use App\Modules\Academico\Http\Requests\MaterialTurma\StoreMaterialTurmaRequest;
use App\Modules\Academico\Http\Requests\MaterialTurma\UpdateMaterialTurmaRequest;
use App\Modules\Academico\Http\Resources\MaterialTurmaResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class MaterialTurmaController extends Controller
{
    #[OA\Get(
        path: "/api/materiais-turmas",
        summary: "Listar todos os materiais das turmas",
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
                            items: new OA\Items(ref: "#/components/schemas/MaterialTurmaResource")
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
        $materiais = MaterialTurma::with('turma')->visivelPara(request()->user())->paginate(15);
        return MaterialTurmaResource::collection($materiais);
    }

    #[OA\Post(
        path: "/api/materiais-turmas",
        summary: "Criar novo material de turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(ref: "#/components/schemas/StoreMaterialTurmaRequest")
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Material criado",
                content: new OA\JsonContent(ref: "#/components/schemas/MaterialTurmaResource")
            ),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function store(StoreMaterialTurmaRequest $request)
    {
        $data = $request->validated();

        Gate::authorize('gerenciarMateriais', Turma::findOrFail($data['id_turma']));

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $data['file_type'] = $file->getClientMimeType();
            $path = $file->store('materiais_turmas/' . $data['id_turma'], 'public');
            $data['file_path'] = $path;
        }

        $material = MaterialTurma::create($data);
        return new MaterialTurmaResource($material);
    }

    #[OA\Get(
        path: "/api/materiais-turmas/{id}",
        summary: "Exibir material específico",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operação bem-sucedida",
                content: new OA\JsonContent(ref: "#/components/schemas/MaterialTurmaResource")
            ),
            new OA\Response(response: 404, description: "Material não encontrado")
        ]
    )]
    public function show($id)
    {
        $material = MaterialTurma::with('turma')->visivelPara(request()->user())->findOrFail($id);
        return new MaterialTurmaResource($material);
    }

    #[OA\Post(
        path: "/api/materiais-turmas/{id}",
        summary: "Atualizar material (usar POST com _method=PUT para form-data)",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "_method", in: "query", required: true, schema: new OA\Schema(type: "string", default: "PUT"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(ref: "#/components/schemas/UpdateMaterialTurmaRequest")
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Material atualizado",
                content: new OA\JsonContent(ref: "#/components/schemas/MaterialTurmaResource")
            ),
            new OA\Response(response: 404, description: "Material não encontrado"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function update(UpdateMaterialTurmaRequest $request, $id)
    {
        $material = MaterialTurma::visivelPara($request->user())->findOrFail($id);
        $data = $request->validated();

        if ($request->hasFile('file')) {
            // Remove o antigo
            if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
                Storage::disk('public')->delete($material->file_path);
            }

            $file = $request->file('file');
            $data['file_type'] = $file->getClientMimeType();
            $path = $file->store('materiais_turmas/' . $material->id_turma, 'public');
            $data['file_path'] = $path;
        }

        $material->update($data);
        return new MaterialTurmaResource($material);
    }

    #[OA\Delete(
        path: "/api/materiais-turmas/{id}",
        summary: "Excluir material",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Material excluído"),
            new OA\Response(response: 404, description: "Material não encontrado")
        ]
    )]
    public function destroy($id)
    {
        $material = MaterialTurma::findOrFail($id);

        Gate::authorize('delete', $material);

        if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();
        return response()->json(null, 204);
    }

    #[OA\Get(
        path: "/api/turmas/{turma}/materiais",
        summary: "Obter materiais de uma turma específica",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "turma", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Materiais da Turma",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/MaterialTurmaResource")
                )
            ),
            new OA\Response(response: 404, description: "Turma não encontrada")
        ]
    )]
    public function getByTurma($turmaId)
    {
        Turma::findOrFail($turmaId);
        $materiais = MaterialTurma::where('id_turma', $turmaId)->visivelPara(request()->user())->get();
        return MaterialTurmaResource::collection($materiais);
    }
}
