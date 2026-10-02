<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Http\Requests\Aula\StoreAulaTurmaRequest;
use App\Modules\Academico\Http\Requests\Aula\UpdateAulaTurmaRequest;
use App\Modules\Academico\Http\Resources\AulaTurmaResource;
use App\Enums\TurmaStatus;
use App\Modules\Academico\Services\AulaSnapshotService;
use App\Modules\Academico\UseCases\Aula\CreateAulaTurmaUseCase;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: "Academico",
    description: "Gerenciamento de Aulas das Turmas"
)]
class AulaTurmaController extends Controller
{
    public function __construct(
        private readonly CreateAulaTurmaUseCase $createAulaTurma,
        private readonly AulaSnapshotService $snapshots,
    ) {
    }

    #[OA\Get(
        path: "/api/aulas-turmas",
        summary: "Listar aulas das turmas",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de aulas",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/AulaTurma")
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $query = AulaTurma::with(['turma.curso', 'turma.nivel', 'turma.matriculas.aluno.usuario', 'professor.usuario', 'aluno_especifico.usuario', 'curso', 'nivel', 'conta'])
            ->visivelPara($request->user());

        if ($request->has('data_inicio')) {
            $query->where('data', '>=', $request->data_inicio);
        }

        if ($request->has('data_fim')) {
            $query->where('data', '<=', $request->data_fim);
        }

        if ($request->has('id_turma')) {
            $query->where('id_turma', $request->id_turma);
        }

        if ($request->has('id_professor')) {
            $query->where('id_professor', $request->id_professor);
        }

        if ($request->has('id_aluno')) {
            $idAluno = $request->id_aluno;
            $query->where(function ($q) use ($idAluno) {
                // Aulas de reposição/reforço onde o aluno é o específico
                $q->where('id_aluno_especifico', $idAluno)
                  // Aulas da turma inteira em que o aluno está matriculado (sem outro aluno específico)
                  ->orWhere(function ($turmaQuery) use ($idAluno) {
                      $turmaQuery->where(function ($semAluno) {
                          $semAluno->whereNull('id_aluno_especifico')
                                   ->orWhere('id_aluno_especifico', 0);
                      })->whereHas('turma', function ($tq) use ($idAluno) {
                          $tq->whereHas('matriculas', function ($mq) use ($idAluno) {
                              $mq->where('id_aluno', $idAluno)
                                 ->where('status', 'ativa');
                          });
                      });
                  });
            });
        }

        // Para a agenda, geralmente queremos todos os eventos do período sem paginação forte
        // ou uma paginação bem larga. Se tiver filtros de data, desativamos a paginação para o frontend da agenda.
        if ($request->has('data_inicio') || $request->has('data_fim')) {
            $aulas = $query->get();
            return AulaTurmaResource::collection($aulas);
        }

        $aulas = $query->paginate($request->get('per_page', 15));
        return AulaTurmaResource::collection($aulas);
    }

    #[OA\Post(
        path: "/api/aulas-turmas",
        summary: "Criar uma aula para uma turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreAulaTurmaRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Aula criada com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/AulaTurma")
            )
        ]
    )]
    public function store(StoreAulaTurmaRequest $request)
    {
        $data = $request->validated();

        Gate::authorize('create', [AulaTurma::class, (int) $data['id_professor']]);
        $this->checkBusinessRules(null, $data['id_turma'] ?? null);

        $aulas = $this->createAulaTurma->execute($data);

        return new AulaTurmaResource($aulas[0]);
    }

    #[OA\Get(
        path: "/api/aulas-turmas/{id}",
        summary: "Exibir detalhes de uma aula",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Detalhes da aula",
                content: new OA\JsonContent(ref: "#/components/schemas/AulaTurma")
            ),
            new OA\Response(response: 403, description: "Aula de turma que o professor não leciona")
        ]
    )]
    public function show(AulaTurma $aulaTurma)
    {
        Gate::authorize('view', $aulaTurma);

        return new AulaTurmaResource($aulaTurma->load(['turma.matriculas.aluno.usuario', 'turma.curso', 'turma.nivel', 'professor', 'conta']));
    }

    #[OA\Put(
        path: "/api/aulas-turmas/{id}",
        summary: "Atualizar uma aula",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateAulaTurmaRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Aula atualizada com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/AulaTurma")
            ),
            new OA\Response(response: 403, description: "Ação não permitida")
        ]
    )]
    public function update(UpdateAulaTurmaRequest $request, AulaTurma $aulaTurma)
    {
        $this->checkBusinessRules($aulaTurma);
        Gate::authorize('update', $aulaTurma);

        if ($request->validated('status') === 'cancelada' && $aulaTurma->status !== 'cancelada') {
            Gate::authorize('delete', $aulaTurma);
        }

        $aulaTurma->update($this->snapshots->aplicarSeConcluida($request->validated(), $aulaTurma));
        return new AulaTurmaResource($aulaTurma);
    }

    #[OA\Delete(
        path: "/api/aulas-turmas/{id}",
        summary: "Excluir uma aula (Soft Delete)",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Aula excluída com sucesso"),
            new OA\Response(response: 403, description: "Ação não permitida")
        ]
    )]
    public function destroy(Request $request, AulaTurma $aulaTurma)
    {
        $this->checkBusinessRules($aulaTurma);
        Gate::authorize('delete', $aulaTurma);

        if ($request->boolean('excluir_conta')) {
            $aulaTurma->conta()->delete();
        }

        $aulaTurma->delete();
        return response()->noContent();
    }

    private function checkBusinessRules(?AulaTurma $aulaTurma, ?int $turmaId = null)
    {
        $idTurma = $turmaId ?? ($aulaTurma ? $aulaTurma->id_turma : null);

        if ($aulaTurma && $aulaTurma->status === 'concluida') {
            abort(Response::HTTP_FORBIDDEN, 'Aulas dadas e finalizadas só podem ser alteradas por administradores.');
        }

        $turma = null;
        if ($idTurma) {
            $turma = $aulaTurma ? $aulaTurma->turma : \App\Models\Turma::find($idTurma);

            if ($turma && ($turma->status === TurmaStatus::CONCLUIDA || $turma->status === TurmaStatus::CANCELADA)) {
                abort(Response::HTTP_FORBIDDEN, 'Não é possível operar em aulas de turmas concluídas ou canceladas.');
            }

            if ($turma) {
                Gate::authorize('gerenciarAulas', $turma);
            }
        }
    }
}
