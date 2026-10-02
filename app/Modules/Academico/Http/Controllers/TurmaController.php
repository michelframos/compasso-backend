<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Modules\Academico\Http\Requests\Turma\UpdateTurmaStatusRequest;
use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Turma\StoreTurmaRequest;
use App\Modules\Academico\Http\Requests\Turma\UpdateTurmaRequest;
use App\Modules\Academico\Http\Resources\TurmaResource;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Models\TurmaHorario;
use App\Modules\Academico\Services\ClassSchedulingService;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class TurmaController extends Controller
{
    use ResolvesSorting;

    protected $schedulingService;

    public function __construct(ClassSchedulingService $schedulingService)
    {
        $this->schedulingService = $schedulingService;
    }
    #[OA\Get(
        path: "/api/turmas",
        summary: "Listar turmas",
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
                            items: new OA\Items(ref: "#/components/schemas/TurmaResource")
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
        $user = \Illuminate\Support\Facades\Auth::user();
        $sort_by = request('sort_by', 'id');
        $sort_order = $this->sortOrder('desc');
        $search = request('search');
        $status = request('status');

        $query = Turma::with(['curso', 'nivel', 'professor.usuario'])
            ->withCount('matriculas')
            ->visivelPara($user);

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('curso', function ($cq) use ($search) {
                    $cq->where('nome', 'like', "%{$search}%");
                })
                    ->orWhereHas('nivel', function ($nq) use ($search) {
                        $nq->where('nome', 'like', "%{$search}%");
                    })
                    ->orWhereHas('professor.usuario', function ($uq) use ($search) {
                        $uq->where('nome', 'like', "%{$search}%");
                    })
                    ->orWhere('descricao', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        if (in_array($sort_by, ['id', 'status', 'maximo_alunos', 'valor_mensalidade'])) {
            $query->orderBy($sort_by, $sort_order);
        } elseif ($sort_by === 'curso.nome') {
            $query->join('cursos', 'turmas.id_curso', '=', 'cursos.id')
                ->orderBy('cursos.nome', $sort_order)
                ->select('turmas.*');
        } elseif ($sort_by === 'nivel.nome') {
            $query->join('niveis', 'turmas.id_nivel', '=', 'niveis.id')
                ->orderBy('niveis.nome', $sort_order)
                ->select('turmas.*');
        } else {
            $query->orderBy('id', 'desc');
        }

        $turmas = $query->paginate(15);
        return TurmaResource::collection($turmas);
    }

    #[OA\Post(
        path: "/api/turmas",
        summary: "Criar nova turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreTurmaRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Turma criada",
                content: new OA\JsonContent(ref: "#/components/schemas/TurmaResource")
            ),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function store(StoreTurmaRequest $request)
    {
        $data = $request->validated();
        $horariosData = $data['horarios'] ?? [];
        unset($data['horarios']);

        $turma = Turma::create($data);

        if (!empty($horariosData)) {
            foreach ($horariosData as $h) {
                $turma->horarios()->create($h);
            }

            // Disparar agendamento automático
            $this->schedulingService->generateClasses($turma);
        }

        return new TurmaResource($turma->load('horarios'));
    }

    #[OA\Get(
        path: "/api/turmas/{id}",
        summary: "Exibir turma específica",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operação bem-sucedida",
                content: new OA\JsonContent(ref: "#/components/schemas/TurmaResource")
            ),
            new OA\Response(response: 404, description: "Turma não encontrada")
        ]
    )]
    public function show($id)
    {
        $turma = Turma::with(['curso', 'nivel', 'professor.usuario', 'horarios', 'matriculas.aluno.usuario'])->findOrFail($id);

        Gate::authorize('view', $turma);

        return new TurmaResource($turma);
    }

    #[OA\Put(
        path: "/api/turmas/{id}",
        summary: "Atualizar turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateTurmaRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Turma atualizada",
                content: new OA\JsonContent(ref: "#/components/schemas/TurmaResource")
            ),
            new OA\Response(response: 404, description: "Turma não encontrada"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function update(UpdateTurmaRequest $request, $id)
    {
        $turma = Turma::findOrFail($id);
        $data = $request->validated();
        $horariosData = $data['horarios'] ?? null;
        unset($data['horarios']);

        $turma->update($data);

        if ($horariosData !== null) {
            // Sincronizar horários: remove antigos e cria novos
            $turma->horarios()->delete();
            foreach ($horariosData as $h) {
                $turma->horarios()->create($h);
            }

            // Re-disparar agendamento se necessário (lógica simples: gera o que falta)
            $this->schedulingService->generateClasses($turma);
        }

        return new TurmaResource($turma->load('horarios'));
    }

    #[OA\Patch(
        path: "/api/turmas/{id}/status",
        summary: "Atualizar status da turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateTurmaStatusRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Status da turma atualizado",
                content: new OA\JsonContent(ref: "#/components/schemas/TurmaResource")
            ),
            new OA\Response(response: 403, description: "Ação não permitida na turma atual"),
            new OA\Response(response: 404, description: "Turma não encontrada"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function updateStatus(UpdateTurmaStatusRequest $request, $id)
    {
        $turma = Turma::findOrFail($id);
        $turma->update(['status' => $request->status]);
        return new TurmaResource($turma);
    }

    #[OA\Delete(
        path: "/api/turmas/{id}",
        summary: "Excluir turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Turma excluída"),
            new OA\Response(response: 404, description: "Turma não encontrada")
        ]
    )]
    public function destroy($id)
    {
        $turma = Turma::findOrFail($id);
        $turma->delete();
        return response()->json(null, 204);
    }
}
