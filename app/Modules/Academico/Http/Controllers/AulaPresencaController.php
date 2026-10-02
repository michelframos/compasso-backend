<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\AulaPresenca;
use App\Modules\Academico\Models\Turma;
use App\Enums\TurmaStatus;
use App\Modules\Academico\Http\Requests\Aula\SyncAulaPresencaRequest;
use App\Modules\Academico\Http\Resources\AulaPresencaResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Academico", description: "Gerenciamento de Presenças nas Aulas")]
class AulaPresencaController extends Controller
{
    #[OA\Get(
        path: "/api/aulas-turmas/{aulaTurma}/presencas",
        summary: "Listar presenças de uma aula",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "aulaTurma", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de presenças",
                content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/AulaPresenca"))
            )
        ]
    )]
    public function index(AulaTurma $aulaTurma)
    {
        Gate::authorize('viewPresencas', $aulaTurma);

        $presencas = $aulaTurma->presencas()->with('aluno')->visivelPara(request()->user())->get();
        return AulaPresencaResource::collection($presencas);
    }

    #[OA\Post(
        path: "/api/aulas-turmas/{aulaTurma}/presencas/sync",
        summary: "Sincronizar presenças de uma aula (Lote)",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "aulaTurma", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/SyncAulaPresencaRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Presenças sincronizadas com sucesso"),
            new OA\Response(response: 403, description: "Ação não permitida")
        ]
    )]
    public function bulkStore(SyncAulaPresencaRequest $request, AulaTurma $aulaTurma)
    {
        $this->checkBusinessRules($aulaTurma);

        $data = $request->validated();

        $mapFrontendToDb = [
            'presente' => 'presente',
            'falta' => 'ausente',
            'falta_justificada' => 'justificado',
        ];

        // Atualiza o conteúdo da aula se fornecido
        if (array_key_exists('conteudo_dado', $data)) {
            $aulaTurma->update(['conteudo_dado' => $data['conteudo_dado']]);
        }

        foreach ($data['presencas'] as $presencaData) {
            AulaPresenca::updateOrCreate(
                [
                    'id_aula_turma' => $aulaTurma->id,
                    'id_aluno' => $presencaData['id_aluno'],
                ],
                ['status' => $mapFrontendToDb[$presencaData['status']] ?? $presencaData['status'], 'observacao' => $presencaData['observacao'] ?? null]
            );
        }

        return response()->json(['message' => 'Presenças sincronizadas com sucesso.']);
    }

    #[OA\Delete(
        path: "/api/presencas/{aulaPresenca}",
        summary: "Excluir uma presença específica",
        tags: ["Academico"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "aulaPresenca",
                in: "path",
                description: "ID da presença",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 204, description: "Presença excluída com sucesso"),
            new OA\Response(response: 403, description: "Operação não permitida (turma concluída ou cancelada)"),
            new OA\Response(response: 404, description: "Presença não encontrada")
        ]
    )]
    public function destroy(AulaPresenca $aulaPresenca)
    {
        if (!$aulaPresenca->aula) {
            abort(Response::HTTP_NOT_FOUND, 'Aula não encontrada.');
        }

        $this->checkBusinessRules($aulaPresenca->aula);

        $aulaPresenca->delete();

        return response()->noContent();
    }

    #[OA\Delete(
        path: "/api/aulas-turmas/{aulaTurma}/presencas",
        summary: "Excluir todas as presenças de uma aula",
        tags: ["Academico"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: "aulaTurma",
                in: "path",
                description: "ID da aula",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 204, description: "Todas as presenças da aula foram excluídas"),
            new OA\Response(response: 403, description: "Operação não permitida (turma concluída ou cancelada)"),
            new OA\Response(response: 404, description: "Aula não encontrada")
        ]
    )]
    public function destroyAll(AulaTurma $aulaTurma)
    {
        $this->checkBusinessRules($aulaTurma);

        $aulaTurma->presencas()->delete();

        return response()->noContent();
    }

    private function checkBusinessRules(AulaTurma $aulaTurma)
    {
        Gate::authorize('registrarPresencas', $aulaTurma);

        $turma = $aulaTurma->turma;
        $turmaBloqueada = $turma && (
            $turma->status === TurmaStatus::CONCLUIDA ||
            $turma->status === TurmaStatus::CANCELADA
        );

        if ($turmaBloqueada || $aulaTurma->status === 'concluida') {
            abort(Response::HTTP_FORBIDDEN, 'Não é possível alterar presenças de turmas concluídas ou canceladas.');
        }
    }
}
