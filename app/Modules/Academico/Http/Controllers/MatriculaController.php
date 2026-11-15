<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Http\Requests\Matricula\StoreMatriculaRequest;
use App\Modules\Academico\Http\Requests\Matricula\UpdateMatriculaRequest;
use App\Modules\Academico\Http\Requests\Matricula\GerarMensalidadesRequest;
use App\Modules\Academico\Http\Resources\MatriculaResource;
use App\Modules\Academico\Queries\ListMatriculasQuery;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\UseCases\Matricula\AjustarMensalidadesNaMudancaTurmaUseCase;
use App\Modules\Academico\UseCases\Matricula\CreateMatriculaUseCase;
use App\Modules\Academico\UseCases\Matricula\GerarMensalidadesDaMatriculaUseCase;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Academico", description: "Gerenciamento de Matrículas")]
class MatriculaController extends Controller
{
    use ResolvesSorting;

    private const CAMPOS_MENSALIDADE_UPDATE = [
        'reajustar_mensalidades',
        'gerar_novas_mensalidades',
        'valor_mensalidade_geracao',
        'quantidade_parcelas',
        'dia_vencimento',
        'data_inicio_mensalidades',
        'id_categoria_mensalidade',
        'observacoes_mensalidade',
    ];

    public function __construct(
        private readonly ListMatriculasQuery $listMatriculas,
        private readonly CreateMatriculaUseCase $createMatricula,
        private readonly GerarMensalidadesDaMatriculaUseCase $gerarMensalidades,
        private readonly AjustarMensalidadesNaMudancaTurmaUseCase $ajustarMensalidades,
    ) {
    }

    #[OA\Get(
        path: "/api/matriculas",
        summary: "Listar todas as matrículas",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de matrículas",
                content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Matricula"))
            )
        ]
    )]
    public function index()
    {
        $query = Matricula::query()->visivelPara(request()->user());

        $matriculas = $this->listMatriculas
            ->build($query, request()->only(['status', 'search', 'sort_by']), $this->sortOrder('desc'))
            ->paginate(15);
        return MatriculaResource::collection($matriculas);
    }

    #[OA\Get(
        path: "/api/alunos/{aluno}/matriculas",
        summary: "Listar matrículas de um aluno",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "aluno", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de matrículas do aluno",
                content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Matricula"))
            )
        ]
    )]
    public function getByAluno($id_aluno)
    {
        Gate::authorize('viewAnyDoAluno', [Matricula::class, (int) $id_aluno]);

        $matriculas = Matricula::where('id_aluno', $id_aluno)->with(['aluno', 'turma'])->get();
        return MatriculaResource::collection($matriculas);
    }

    #[OA\Get(
        path: "/api/turmas/{turma}/matriculas",
        summary: "Listar matrículas de uma turma",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "turma", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de matrículas da turma",
                content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/Matricula"))
            )
        ]
    )]
    public function getByTurma($id_turma)
    {
        Gate::authorize('viewAnyDaTurma', [Matricula::class, (int) $id_turma]);

        $matriculas = Matricula::where('id_turma', $id_turma)
            ->visivelPara(request()->user())
            ->with(['aluno', 'turma'])
            ->get();
        return MatriculaResource::collection($matriculas);
    }

    #[OA\Post(
        path: "/api/matriculas",
        summary: "Criar uma nova matrícula",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreMatriculaRequest")
        ),
        responses: [
            new OA\Response(response: 201, description: "Matrícula criada com sucesso", content: new OA\JsonContent(ref: "#/components/schemas/Matricula")),
            new OA\Response(response: 422, description: "Erro de validação (turma concluída, lotada, etc)")
        ]
    )]
    public function store(StoreMatriculaRequest $request)
    {
        $matricula = $this->createMatricula->execute($request->validated());

        return (new MatriculaResource($matricula))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: "/api/matriculas/{matricula}",
        summary: "Exibir detalhes de uma matrícula",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "matricula", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Detalhes da matrícula", content: new OA\JsonContent(ref: "#/components/schemas/Matricula")),
            new OA\Response(response: 404, description: "Matrícula não encontrada")
        ]
    )]
    public function show(Matricula $matricula)
    {
        Gate::authorize('view', $matricula);

        return new MatriculaResource($matricula->load(['aluno.usuario', 'turma.curso', 'turma.nivel', 'curso', 'nivel', 'professor.usuario']));
    }

    #[OA\Put(
        path: "/api/matriculas/{matricula}",
        summary: "Atualizar uma matrícula",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "matricula", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateMatriculaRequest")
        ),
        responses: [
            new OA\Response(response: 200, description: "Matrícula atualizada com sucesso", content: new OA\JsonContent(ref: "#/components/schemas/Matricula")),
            new OA\Response(response: 404, description: "Matrícula não encontrada"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function update(UpdateMatriculaRequest $request, Matricula $matricula)
    {
        $validated = $request->validated();
        $opcoesMensalidade = array_intersect_key($validated, array_flip(self::CAMPOS_MENSALIDADE_UPDATE));
        $dados = array_diff_key($validated, array_flip(self::CAMPOS_MENSALIDADE_UPDATE));

        $turmaAnterior = $matricula->id_turma;
        $matricula->update($dados);

        $turmaNova = $dados['id_turma'] ?? null;
        if ($turmaNova !== null && (int) $turmaNova !== (int) $turmaAnterior) {
            $turmaDestino = Turma::query()->find((int) $turmaNova);
            if ($turmaDestino !== null) {
                $this->ajustarMensalidades->execute($matricula->fresh(['aluno.usuario']), $turmaDestino, [
                    'reajustar_mensalidades' => (bool) ($opcoesMensalidade['reajustar_mensalidades'] ?? false),
                    'gerar_novas_mensalidades' => (bool) ($opcoesMensalidade['gerar_novas_mensalidades'] ?? false),
                    'valor' => $opcoesMensalidade['valor_mensalidade_geracao'] ?? null,
                    'quantidade_parcelas' => $opcoesMensalidade['quantidade_parcelas'] ?? null,
                    'dia_vencimento' => $opcoesMensalidade['dia_vencimento'] ?? null,
                    'data_inicio' => $opcoesMensalidade['data_inicio_mensalidades'] ?? null,
                    'id_categoria' => $opcoesMensalidade['id_categoria_mensalidade'] ?? null,
                    'observacoes' => $opcoesMensalidade['observacoes_mensalidade'] ?? null,
                ]);
            }
        }

        return new MatriculaResource($matricula->load(['aluno.usuario', 'turma.curso', 'turma.nivel', 'curso', 'nivel', 'professor.usuario']));
    }

    #[OA\Delete(
        path: "/api/matriculas/{matricula}",
        summary: "Excluir uma matrícula",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "matricula", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Matrícula excluída com sucesso"),
            new OA\Response(response: 404, description: "Matrícula não encontrada")
        ]
    )]
    public function destroy(Matricula $matricula)
    {
        $matricula->delete();
        return response()->noContent();
    }

    #[OA\Get(
        path: "/api/matriculas/{matricula}/historico",
        summary: "Obter histórico de transferências de uma matrícula",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "matricula", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Histórico de transferências",
                content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/MatriculaHistorico"))
            ),
            new OA\Response(response: 404, description: "Matrícula não encontrada")
        ]
    )]
    public function getHistorico(Matricula $matricula)
    {
        $historico = $matricula->historicos()
            ->with(['turmaOrigem.curso', 'turmaOrigem.nivel', 'turmaDestino.curso', 'turmaDestino.nivel'])
            ->orderBy('created_at', 'desc')
            ->get();
        return \App\Modules\Academico\Http\Resources\MatriculaHistoricoResource::collection($historico);
    }

    #[OA\Get(
        path: "/api/matriculas/{matricula}/mensalidades",
        summary: "Listar mensalidades de uma matrícula",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "matricula", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de mensalidades do aluno da matrícula",
                content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/ContaResource"))
            ),
            new OA\Response(response: 404, description: "Matrícula não encontrada")
        ]
    )]
    public function getMensalidades(Matricula $matricula)
    {
        Gate::authorize('viewMensalidades', $matricula);

        $contas = \App\Models\Conta::with(['categoria', 'pagamentos'])
            ->where(function ($query) use ($matricula) {
                $query->where('id_matricula', $matricula->id)
                    ->orWhere(function ($q) use ($matricula) {
                        $q->whereNull('id_matricula')
                            ->where('id_aluno', $matricula->id_aluno);
                    });
            })
            ->orderBy('data_vencimento', 'desc')
            ->paginate(20);

        return \App\Modules\Financeiro\Http\Resources\ContaResource::collection($contas);
    }

    #[OA\Post(
        path: "/api/matriculas/{matricula}/gerar-mensalidades",
        summary: "Gerar mensalidades para uma matrícula",
        security: [["sanctum" => []]],
        tags: ["Academico"],
        parameters: [
            new OA\Parameter(name: "matricula", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/GerarMensalidadesRequest")
        ),
        responses: [
            new OA\Response(response: 201, description: "Mensalidades geradas com sucesso"),
            new OA\Response(response: 404, description: "Matrícula não encontrada"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function gerarMensalidades(GerarMensalidadesRequest $request, Matricula $matricula)
    {
        $contasGeradas = $this->gerarMensalidades->execute($matricula, $request->validated());

        return response()->json([
            'message' => 'Mensalidades geradas com sucesso!',
            'count' => count($contasGeradas),
        ], 201);
    }
}
