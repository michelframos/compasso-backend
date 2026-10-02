<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Academico\Models\TurmaHorario;
use App\Modules\Academico\Models\Turma;
use App\Enums\TurmaStatus;
use App\Modules\Academico\Http\Requests\TurmaHorario\StoreTurmaHorarioRequest;
use App\Modules\Academico\Http\Requests\TurmaHorario\UpdateTurmaHorarioRequest;
use App\Modules\Academico\Http\Resources\TurmaHorarioResource;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TurmaHorarioController extends Controller
{
    #[OA\Get(
        path: "/api/turma-horarios",
        summary: "Listar horários de uma turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id_turma", in: "query", required: true, schema: new OA\Schema(type: "integer"))
        ],
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
                            items: new OA\Items(ref: "#/components/schemas/TurmaHorarioResource")
                        )
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function index(Request $request)
    {
        $request->validate(['id_turma' => ['required', InstituicaoContext::existsRule('turmas')]]);
        $horarios = TurmaHorario::where('id_turma', $request->id_turma)->get();
        return TurmaHorarioResource::collection($horarios);
    }

    #[OA\Post(
        path: "/api/turma-horarios",
        summary: "Adicionar horário em uma turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreTurmaHorarioRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Horário adicionado com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/TurmaHorarioResource")
            ),
            new OA\Response(response: 403, description: "Ação não permitida na turma atual"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function store(StoreTurmaHorarioRequest $request)
    {
        $turma = Turma::findOrFail($request->id_turma);

        if (in_array($turma->status, [TurmaStatus::CANCELADA, TurmaStatus::CONCLUIDA])) {
            return response()->json(['message' => 'Turma cancelada ou concluída não pode receber novos horários.'], 403);
        }

        $turmaHorario = TurmaHorario::create($request->validated());
        return new TurmaHorarioResource($turmaHorario);
    }

    #[OA\Put(
        path: "/api/turma-horarios/{id}",
        summary: "Atualizar horário da turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateTurmaHorarioRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Horário atualizado com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/TurmaHorarioResource")
            ),
            new OA\Response(response: 403, description: "Ação não permitida na turma atual"),
            new OA\Response(response: 404, description: "Horário não encontrado"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function update(UpdateTurmaHorarioRequest $request, TurmaHorario $turmaHorario)
    {
        $turma = $turmaHorario->turma;
        if (in_array($turma->status, [TurmaStatus::CANCELADA, TurmaStatus::CONCLUIDA])) {
            return response()->json(['message' => 'Não é possível alterar horários de uma turma cancelada ou concluída.'], 403);
        }

        $turmaHorario->update($request->validated());
        return new TurmaHorarioResource($turmaHorario);
    }

    #[OA\Delete(
        path: "/api/turma-horarios/{id}",
        summary: "Excluir horário da turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Horário excluído com sucesso"),
            new OA\Response(response: 403, description: "Ação não permitida na turma atual"),
            new OA\Response(response: 404, description: "Horário não encontrado")
        ]
    )]
    public function destroy(TurmaHorario $turmaHorario)
    {
        $turma = $turmaHorario->turma;
        if (in_array($turma->status, [TurmaStatus::CANCELADA, TurmaStatus::CONCLUIDA])) {
            return response()->json(['message' => 'Não é possível excluir horários de uma turma cancelada ou concluída.'], 403);
        }

        $turmaHorario->delete();
        return response()->noContent();
    }
}
