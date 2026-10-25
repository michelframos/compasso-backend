<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\SolicitacaoAula\DecidirSolicitacaoAulaRequest;
use App\Modules\Academico\Http\Requests\SolicitacaoAula\ListSolicitacoesAulasRequest;
use App\Modules\Academico\Http\Requests\SolicitacaoAula\StoreSolicitacaoAulaRequest;
use App\Modules\Academico\Http\Resources\SolicitacaoAulaResource;
use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\SolicitacaoAula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Queries\ListSolicitacoesAulasQuery;
use App\Modules\Academico\Services\RemanejamentoAulaService;
use App\Modules\Academico\UseCases\SolicitacaoAula\CancelarSolicitacaoAulaUseCase;
use App\Modules\Academico\UseCases\SolicitacaoAula\DecidirSolicitacaoAulaUseCase;
use App\Modules\Academico\UseCases\SolicitacaoAula\SolicitarAlteracaoAulaUseCase;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\Support\PermissoesProfessorAulas;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class SolicitacaoAulaController extends Controller
{
    public function __construct(private readonly RemanejamentoAulaService $remanejamento) {}

    #[OA\Get(
        path: '/api/professor/me/solicitacoes',
        summary: 'Solicitações de aula feitas pelo professor autenticado',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Padrão: todas', schema: new OA\Schema(type: 'string', enum: ['pendente', 'aprovada', 'rejeitada', 'cancelada', 'todas'])),
            new OA\Parameter(name: 'tipo', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['criacao', 'cancelamento', 'reposicao', 'substituicao'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Solicitações paginadas (15 por página), mais recentes primeiro', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SolicitacaoAulaResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Usuário não é professor desta instituição'),
        ]
    )]
    public function minhas(ListSolicitacoesAulasRequest $request, ListSolicitacoesAulasQuery $query)
    {
        $filtros = $request->safe()->except('search') + ['id_professor' => $request->user()->professor->id];
        $solicitacoes = $query->build($filtros, 'todas')->paginate(15);

        $this->analisarPendentes($solicitacoes->getCollection());

        return SolicitacaoAulaResource::collection($solicitacoes);
    }

    #[OA\Get(
        path: '/api/professor/me/aulas/{aulaTurma}/opcoes-solicitacao',
        summary: 'O que o professor pode pedir para a aula: permissões da escola, cobrança e solicitação pendente',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'aulaTurma', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Opções', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'permissoes', ref: '#/components/schemas/UpdatePermissoesProfessorRequest'),
                    new OA\Property(property: 'tem_cobranca', type: 'boolean', description: 'A aula tem cobrança avulsa: ao cancelar ou repor, o professor escolhe o destino'),
                    new OA\Property(property: 'proxima_aula_cobranca', type: 'string', nullable: true, description: 'Aula que receberia a cobrança no cancelamento'),
                    new OA\Property(property: 'solicitacao_pendente', ref: '#/components/schemas/SolicitacaoAulaResource', nullable: true),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Aula que o professor não leciona'),
            new OA\Response(response: 404, description: 'Aula não encontrada'),
        ]
    )]
    public function opcoes(AulaTurma $aulaTurma)
    {
        Gate::authorize('view', $aulaTurma);

        $temCobranca = $this->remanejamento->temCobranca($aulaTurma);
        $proxima = $temCobranca ? $this->remanejamento->proximaAulaParaCobranca($aulaTurma) : null;
        $pendente = SolicitacaoAula::query()
            ->with(SolicitacaoAula::DETALHES)
            ->where('id_aula_turma', $aulaTurma->id)
            ->where('status', SolicitacaoAula::STATUS_PENDENTE)
            ->first();

        return response()->json(['data' => [
            'permissoes' => PermissoesProfessorAulas::da(InstituicaoContext::instituicao()),
            'tem_cobranca' => $temCobranca,
            'proxima_aula_cobranca' => $proxima ? $this->remanejamento->descrever($proxima) : null,
            'solicitacao_pendente' => $pendente ? new SolicitacaoAulaResource($pendente) : null,
        ]]);
    }

    #[OA\Post(
        path: '/api/professor/me/solicitacoes',
        summary: 'Solicitar criação, cancelamento, reposição ou substituição de aula',
        description: 'Se a escola deixa a ação livre, a alteração é aplicada na hora (status aprovada, aplicada_automaticamente). Se exige aprovação, fica pendente para a secretaria.',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreSolicitacaoAulaRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Solicitação registrada (pendente) ou aplicada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/SolicitacaoAulaResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Aula ou turma de outro professor, ou ação bloqueada pela escola'),
            new OA\Response(response: 422, description: 'Dados inválidos, aula não agendada ou passada, conflito de agenda, solicitação pendente já existente ou destino da cobrança ausente'),
        ]
    )]
    public function store(StoreSolicitacaoAulaRequest $request, SolicitarAlteracaoAulaUseCase $solicitar)
    {
        $dados = $request->validated();
        $aula = isset($dados['id_aula_turma']) ? AulaTurma::with('turma')->find($dados['id_aula_turma']) : null;
        $turma = isset($dados['id_turma']) ? Turma::find($dados['id_turma']) : null;

        Gate::authorize('create', [SolicitacaoAula::class, $dados['tipo'], $aula, $turma]);

        $solicitacao = $solicitar->execute($request->user()->professor, $dados)->load(SolicitacaoAula::DETALHES);
        $this->analisarPendentes(collect([$solicitacao]));

        return (new SolicitacaoAulaResource($solicitacao))->response()->setStatusCode(201);
    }

    #[OA\Delete(
        path: '/api/professor/me/solicitacoes/{solicitacaoAula}',
        summary: 'Cancelar solicitação de aula ainda pendente (somente o autor)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'solicitacaoAula', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Solicitação cancelada'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Solicitação de outro professor'),
            new OA\Response(response: 404, description: 'Solicitação não encontrada'),
            new OA\Response(response: 422, description: 'Solicitação já decidida'),
        ]
    )]
    public function destroy(SolicitacaoAula $solicitacaoAula, CancelarSolicitacaoAulaUseCase $cancelar)
    {
        Gate::authorize('delete', $solicitacaoAula);

        $cancelar->execute($solicitacaoAula);

        return response()->noContent();
    }

    #[OA\Get(
        path: '/api/solicitacoes-aulas',
        summary: 'Solicitações de aula dos professores (fila da secretaria)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Padrão: pendente', schema: new OA\Schema(type: 'string', enum: ['pendente', 'aprovada', 'rejeitada', 'cancelada', 'todas'])),
            new OA\Parameter(name: 'tipo', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['criacao', 'cancelamento', 'reposicao', 'substituicao'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Nome do professor', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Solicitações paginadas (15 por página); pendentes trazem a análise (avisos, cobrança, disponibilidade)', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SolicitacaoAulaResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
            new OA\Response(response: 422, description: 'Filtro inválido'),
        ]
    )]
    public function index(ListSolicitacoesAulasRequest $request, ListSolicitacoesAulasQuery $query)
    {
        Gate::authorize('viewAny', SolicitacaoAula::class);

        $solicitacoes = $query->build($request->validated())->paginate(15);

        $this->analisarPendentes($solicitacoes->getCollection());

        return SolicitacaoAulaResource::collection($solicitacoes);
    }

    #[OA\Post(
        path: '/api/solicitacoes-aulas/{solicitacaoAula}/decisao',
        summary: 'Aprovar (com ajustes opcionais) ou rejeitar solicitação de aula',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'solicitacaoAula', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DecidirSolicitacaoAulaRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Solicitação decidida', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/SolicitacaoAulaResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
            new OA\Response(response: 404, description: 'Solicitação não encontrada'),
            new OA\Response(response: 422, description: 'Já decidida, aula não mais agendada, conflito de agenda, substituto ou destino da cobrança ausente, cobrança já paga ou motivo da rejeição ausente'),
        ]
    )]
    public function decidir(DecidirSolicitacaoAulaRequest $request, SolicitacaoAula $solicitacaoAula, DecidirSolicitacaoAulaUseCase $decidir)
    {
        Gate::authorize('decidir', $solicitacaoAula);

        $solicitacao = $decidir->execute($solicitacaoAula, $request->validated(), $request->user());

        return new SolicitacaoAulaResource($solicitacao->load(SolicitacaoAula::DETALHES));
    }

    private function analisarPendentes($solicitacoes): void
    {
        $solicitacoes
            ->filter(fn (SolicitacaoAula $s) => $s->estaPendente())
            ->each(fn (SolicitacaoAula $s) => $s->setRelation('analise', collect($this->remanejamento->analisar($s))));
    }
}
