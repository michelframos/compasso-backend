<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\AvisoTurma\ListAvisosTurmasRequest;
use App\Modules\Academico\Http\Requests\AvisoTurma\PreviaAvisoTurmaRequest;
use App\Modules\Academico\Http\Requests\AvisoTurma\StoreAvisoTurmaRequest;
use App\Modules\Academico\Http\Resources\AvisoTurmaResource;
use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Queries\ListAvisosTurmasQuery;
use App\Modules\Academico\Services\DestinatariosAvisoService;
use App\Modules\Academico\Services\LimiteAvisosService;
use App\Modules\Academico\UseCases\AvisoTurma\EnviarAvisoTurmaUseCase;
use App\Modules\Core\Contracts\EnviarAvisoTurmaPort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class AvisoTurmaController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me/avisos',
        summary: 'Avisos das turmas do professor e os que ele enviou',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_turma', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'origem', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['manual', 'aula_alterada'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Título ou autor', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Avisos paginados (15 por página), mais recentes primeiro', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AvisoTurmaResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Usuário não é professor desta instituição'),
        ]
    )]
    #[OA\Get(
        path: '/api/avisos',
        summary: 'Histórico de todos os avisos da escola (secretaria/admin)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_turma', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'origem', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['manual', 'aula_alterada'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Título ou autor', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Avisos paginados (15 por página)', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AvisoTurmaResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
        ]
    )]
    public function index(ListAvisosTurmasRequest $request, ListAvisosTurmasQuery $query)
    {
        Gate::authorize('viewAny', AvisoTurma::class);

        return AvisoTurmaResource::collection($query->build($request->user(), $request->validated())->paginate(15));
    }

    #[OA\Get(
        path: '/api/professor/me/avisos/opcoes',
        summary: 'Canais disponíveis e limite diário de avisos do professor',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        responses: [
            new OA\Response(response: 200, description: 'Opções', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'canais', type: 'object', description: 'Canal => disponível (WhatsApp exige conexão em Configurações)', properties: [
                        new OA\Property(property: 'email', type: 'boolean'),
                        new OA\Property(property: 'whatsapp', type: 'boolean'),
                    ]),
                    new OA\Property(property: 'limite', type: 'object', nullable: true, description: 'Nulo para secretaria/admin', properties: [
                        new OA\Property(property: 'por_dia', type: 'integer'),
                        new OA\Property(property: 'enviados_hoje', type: 'integer'),
                        new OA\Property(property: 'restantes', type: 'integer'),
                    ]),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Usuário não é professor desta instituição'),
        ]
    )]
    #[OA\Get(
        path: '/api/avisos/opcoes',
        summary: 'Canais disponíveis para avisos (secretaria/admin, sem limite diário)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        responses: [
            new OA\Response(response: 200, description: 'Opções (limite sempre nulo)'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
        ]
    )]
    public function opcoes(Request $request, EnviarAvisoTurmaPort $envio, LimiteAvisosService $limites)
    {
        Gate::authorize('viewAny', AvisoTurma::class);

        return response()->json(['data' => [
            'canais' => $envio->canaisDisponiveis(),
            'limite' => $limites->situacao($request->user()),
        ]]);
    }

    #[OA\Get(
        path: '/api/professor/me/avisos/previa',
        summary: 'Quantas pessoas receberiam o aviso e quem está sem contato',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_turma', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'ids_alunos[]', in: 'query', required: false, schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))),
            new OA\Parameter(name: 'publico', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['alunos', 'responsaveis', 'ambos'])),
            new OA\Parameter(name: 'canais[]', in: 'query', required: true, schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', enum: ['email', 'whatsapp']))),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Prévia', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'alunos', type: 'integer'),
                    new OA\Property(property: 'mensagens', type: 'integer', description: 'Mensagens que serão enviadas (pessoa × canal com contato)'),
                    new OA\Property(property: 'por_canal', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'integer')),
                    new OA\Property(property: 'sem_contato', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'nome', type: 'string'),
                        new OA\Property(property: 'canal', type: 'string'),
                    ])),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Turma de outro professor'),
            new OA\Response(response: 422, description: 'Alvo ausente ou alunos fora da turma/do professor'),
        ]
    )]
    #[OA\Get(
        path: '/api/avisos/previa',
        summary: 'Prévia de destinatários de um aviso (secretaria/admin)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        responses: [
            new OA\Response(response: 200, description: 'Mesma resposta da prévia do professor'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
            new OA\Response(response: 422, description: 'Alvo ausente'),
        ]
    )]
    public function previa(PreviaAvisoTurmaRequest $request, DestinatariosAvisoService $destinatarios)
    {
        $dados = $request->validated();
        $turma = $this->turma($dados);

        Gate::authorize('create', [AvisoTurma::class, $turma]);

        $alunos = $destinatarios->alunos($request->user(), $turma, array_map('intval', $dados['ids_alunos'] ?? []));
        $linhas = collect($destinatarios->montar($alunos, $dados['publico'], $dados['canais']));
        [$comContato, $semContato] = $linhas->partition(fn (array $linha) => $linha['destino'] !== null);

        return response()->json(['data' => [
            'alunos' => $alunos->count(),
            'mensagens' => $comContato->count(),
            'por_canal' => $comContato->countBy('canal'),
            'sem_contato' => $semContato->map(fn (array $linha) => ['nome' => $linha['nome'], 'canal' => $linha['canal']])->values(),
        ]]);
    }

    #[OA\Post(
        path: '/api/professor/me/avisos',
        summary: 'Enviar aviso por e-mail e/ou WhatsApp a uma turma ou a alunos escolhidos',
        description: 'O envio é enfileirado; o status de cada destinatário aparece no detalhe. O professor tem limite diário definido pela escola.',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreAvisoTurmaRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Aviso registrado e enfileirado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AvisoTurmaResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Turma de outro professor'),
            new OA\Response(response: 422, description: 'Dados inválidos, WhatsApp desconectado, alunos fora da turma/do professor ou nenhum aluno vigente'),
            new OA\Response(response: 429, description: 'Limite diário de avisos atingido'),
        ]
    )]
    #[OA\Post(
        path: '/api/avisos',
        summary: 'Enviar aviso a qualquer turma ou alunos (secretaria/admin, sem limite diário)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreAvisoTurmaRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Aviso registrado e enfileirado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AvisoTurmaResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
            new OA\Response(response: 422, description: 'Dados inválidos, WhatsApp desconectado ou nenhum aluno vigente'),
        ]
    )]
    public function store(StoreAvisoTurmaRequest $request, EnviarAvisoTurmaUseCase $enviar)
    {
        $dados = $request->validated();
        $turma = $this->turma($dados);

        Gate::authorize('create', [AvisoTurma::class, $turma]);

        $aviso = $enviar->execute($request->user(), $dados, $turma);

        return (new AvisoTurmaResource($this->detalhar($aviso)))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/professor/me/avisos/{avisoTurma}',
        summary: 'Detalhe do aviso com o status de cada destinatário',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'avisoTurma', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Aviso', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AvisoTurmaResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Aviso de turma de outro professor'),
            new OA\Response(response: 404, description: 'Aviso não encontrado'),
        ]
    )]
    #[OA\Get(
        path: '/api/avisos/{avisoTurma}',
        summary: 'Detalhe do aviso (secretaria/admin)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'avisoTurma', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Aviso', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AvisoTurmaResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
            new OA\Response(response: 404, description: 'Aviso não encontrado'),
        ]
    )]
    public function show(AvisoTurma $avisoTurma)
    {
        Gate::authorize('view', $avisoTurma);

        return new AvisoTurmaResource($this->detalhar($avisoTurma));
    }

    private function turma(array $dados): ?Turma
    {
        return isset($dados['id_turma']) ? Turma::query()->find($dados['id_turma']) : null;
    }

    private function detalhar(AvisoTurma $aviso): AvisoTurma
    {
        return AvisoTurma::query()
            ->comTotais()
            ->with([...AvisoTurma::DETALHES, 'destinatarios' => fn ($q) => $q->orderBy('nome')->orderBy('canal'), 'destinatarios.aluno.usuario'])
            ->findOrFail($aviso->id);
    }
}
