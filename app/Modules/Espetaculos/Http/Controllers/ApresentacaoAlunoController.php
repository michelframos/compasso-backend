<?php

namespace App\Modules\Espetaculos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\ApresentacaoAluno;
use App\Modules\Espetaculos\Http\Requests\ApresentacaoAluno\StoreApresentacaoAlunoRequest;
use App\Modules\Espetaculos\Http\Requests\ApresentacaoAluno\UpdateApresentacaoAlunoRequest;
use App\Modules\Espetaculos\Http\Requests\ApresentacaoAluno\GerarCobrancasLoteRequest;
use App\Modules\Espetaculos\Http\Resources\ApresentacaoAlunoResource;
use App\Modules\Core\Contracts\CriarCobrancasFigurinoPort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class ApresentacaoAlunoController extends Controller
{
    public function __construct(
        private readonly CriarCobrancasFigurinoPort $criarCobrancasFigurinoPort,
    ) {
    }
    #[OA\Get(
        path: "/api/apresentacoes-alunos",
        summary: "Lista as associações de alunos com apresentações",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
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
                            items: new OA\Items(ref: "#/components/schemas/ApresentacaoAlunoResource")
                        ),
                        new OA\Property(property: "links", type: "object"),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $apresentacoesAlunos = ApresentacaoAluno::query()
            ->visivelPara($request->user())
            ->with(['apresentacao', 'aluno'])
            ->latest()
            ->paginate(15);
        return ApresentacaoAlunoResource::collection($apresentacoesAlunos);
    }

    #[OA\Post(
        path: "/api/apresentacoes-alunos",
        summary: "Cria uma nova associação de aluno a uma apresentação",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/StoreApresentacaoAlunoRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Associação criada com sucesso",
                content: new OA\JsonContent(ref: "#/components/schemas/ApresentacaoAlunoResource")
            ),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function store(StoreApresentacaoAlunoRequest $request)
    {
        $apresentacaoAluno = ApresentacaoAluno::create($request->validated());
        $apresentacaoAluno->load(['apresentacao', 'aluno']);
        return new ApresentacaoAlunoResource($apresentacaoAluno);
    }

    #[OA\Get(
        path: "/api/apresentacoes-alunos/{id}",
        summary: "Exibe os detalhes de uma associação",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operação bem-sucedida",
                content: new OA\JsonContent(ref: "#/components/schemas/ApresentacaoAlunoResource")
            ),
            new OA\Response(response: 403, description: "Participação em apresentação que não envolve o professor"),
            new OA\Response(response: 404, description: "Associação não encontrada")
        ]
    )]
    public function show(ApresentacaoAluno $apresentacaoAluno)
    {
        Gate::authorize('view', $apresentacaoAluno);

        $apresentacaoAluno->load(['apresentacao', 'aluno']);
        return new ApresentacaoAlunoResource($apresentacaoAluno);
    }

    #[OA\Put(
        path: "/api/apresentacoes-alunos/{id}",
        summary: "Atualiza uma associação",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/UpdateApresentacaoAlunoRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Associação atualizada",
                content: new OA\JsonContent(ref: "#/components/schemas/ApresentacaoAlunoResource")
            ),
            new OA\Response(response: 404, description: "Associação não encontrada"),
            new OA\Response(response: 422, description: "Erro de validação")
        ]
    )]
    public function update(UpdateApresentacaoAlunoRequest $request, ApresentacaoAluno $apresentacaoAluno)
    {
        $apresentacaoAluno->update($request->validated());
        $apresentacaoAluno->load(['apresentacao', 'aluno']);
        return new ApresentacaoAlunoResource($apresentacaoAluno);
    }

    #[OA\Delete(
        path: "/api/apresentacoes-alunos/{id}",
        summary: "Exclui uma associação logicamente (Soft Delete)",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Associação excluída"),
            new OA\Response(response: 404, description: "Associação não encontrada")
        ]
    )]
    public function destroy(ApresentacaoAluno $apresentacaoAluno)
    {
        $apresentacaoAluno->delete();
        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/api/apresentacoes/{id}/gerar-cobrancas-figurino",
        summary: "Gera cobranças (faturas) em lote para os figurinos dos alunos selecionados",
        security: [["sanctum" => []]],
        tags: ["Espetaculos"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID da Apresentação", schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/GerarCobrancasLoteRequest")
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Cobranças geradas com sucesso",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "contas_geradas", type: "integer")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Apresentação não encontrada"),
            new OA\Response(response: 422, description: "Erro de validação ou de regra de negócio")
        ]
    )]
    public function gerarCobrancasEmLote(GerarCobrancasLoteRequest $request, Apresentacao $apresentacao)
    {
        $dados = $request->validated();

        try {
            $result = $this->criarCobrancasFigurinoPort->execute([
                'apresentacao_id' => $apresentacao->id,
                'participantes_ids' => $dados['participantes_ids'],
                'data_vencimento' => $dados['data_vencimento'],
            ]);

            return response()->json($result, 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
