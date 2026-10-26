<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\Reposicao\ListReposicoesRequest;
use App\Modules\Academico\Http\Requests\Reposicao\SolicitarReposicaoRequest;
use App\Modules\Academico\Http\Resources\OpcaoReposicaoResource;
use App\Modules\Academico\Http\Resources\ReposicaoDoAlunoResource;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\SolicitacaoReposicao;
use App\Modules\Academico\Queries\OpcoesReposicaoQuery;
use App\Modules\Academico\Support\RegrasReposicaoAluno;
use App\Modules\Academico\UseCases\Reposicao\SolicitarReposicaoUseCase;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlunoReposicaoController extends Controller
{
    public function __construct(private readonly AlunoAlvoResolverPort $alunos) {}

    #[OA\Get(
        path: '/api/aluno/me/reposicoes',
        summary: 'Pedidos de reposição do aluno',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedidos e as regras da escola', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ReposicaoDoAlunoResource')),
                new OA\Property(property: 'regras', type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, sem vínculo ou outra instituição'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function index(ListReposicoesRequest $request, RegrasReposicaoAluno $regras)
    {
        $aluno = $this->aluno($request);
        Gate::authorize('viewAnyDoAluno', [SolicitacaoReposicao::class, $aluno->id]);

        $pedidos = SolicitacaoReposicao::query()
            ->with('destino.turma')
            ->where('id_aluno', $aluno->id)
            ->orderByDesc('id')
            ->get();

        $instituicao = Instituicao::query()->find(InstituicaoContext::id());

        return ReposicaoDoAlunoResource::collection($pedidos)->additional([
            'regras' => [
                'limite_periodo' => $instituicao?->limite_reposicoes_periodo,
                'prazo_dias' => $instituicao?->prazo_reposicao_dias,
                'usados_no_periodo' => $regras->usadosNoPeriodo($aluno->id),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/aluno/me/reposicoes/opcoes',
        summary: 'Aulas futuras com vaga e sem conflito de horário para repor uma falta',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_aula_turma_perdida', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Aulas dos próximos 45 dias', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/OpcaoReposicaoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Aula de outro aluno'),
            new OA\Response(response: 404, description: 'Aula não encontrada'),
        ]
    )]
    public function opcoes(ListReposicoesRequest $request, RegrasReposicaoAluno $regras, OpcoesReposicaoQuery $opcoes)
    {
        $aluno = $this->aluno($request);
        Gate::authorize('viewAnyDoAluno', [SolicitacaoReposicao::class, $aluno->id]);

        if (! $request->filled('id_aula_turma_perdida')) {
            throw ValidationException::withMessages(['id_aula_turma_perdida' => 'Informe a falta que será reposta.']);
        }

        $perdida = AulaTurma::query()->whereKey($request->integer('id_aula_turma_perdida'))->first();

        if ($perdida === null) {
            throw new HttpException(404, 'Aula não encontrada.');
        }

        $regras->aulaPerdida($aluno->id, $perdida->id);

        return OpcaoReposicaoResource::collection($opcoes->listar($aluno->id, $perdida));
    }

    #[OA\Post(
        path: '/api/aluno/me/reposicoes',
        summary: 'Pedir reposição de uma falta',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SolicitarReposicaoRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Pedido registrado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ReposicaoDoAlunoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Aula de outro aluno ou sem vínculo'),
            new OA\Response(response: 404, description: 'Aula não encontrada'),
            new OA\Response(response: 422, description: 'Prazo, limite, vaga, horário ou pedido já existente'),
        ]
    )]
    public function store(SolicitarReposicaoRequest $request, SolicitarReposicaoUseCase $solicitar)
    {
        $idInformado = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;
        $aluno = $this->alunos->resolver($request->user(), $idInformado);
        Gate::authorize('viewAnyDoAluno', [SolicitacaoReposicao::class, $aluno->id]);

        $pedido = $solicitar->execute($aluno->id, $request->safe()->except(['id_aluno']));

        return (new ReposicaoDoAlunoResource($pedido))->response()->setStatusCode(201);
    }

    private function aluno(ListReposicoesRequest $request)
    {
        $idInformado = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;

        return $this->alunos->resolver($request->user(), $idInformado);
    }
}
