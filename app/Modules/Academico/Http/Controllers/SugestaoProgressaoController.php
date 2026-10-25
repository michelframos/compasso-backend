<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\SugestaoProgressao\DecidirSugestaoProgressaoRequest;
use App\Modules\Academico\Http\Requests\SugestaoProgressao\ListSugestoesProgressaoRequest;
use App\Modules\Academico\Http\Requests\SugestaoProgressao\StoreSugestaoProgressaoRequest;
use App\Modules\Academico\Http\Resources\NivelResource;
use App\Modules\Academico\Http\Resources\SugestaoProgressaoResource;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Nivel;
use App\Modules\Academico\Models\SugestaoProgressao;
use App\Modules\Academico\Queries\ListSugestoesProgressaoQuery;
use App\Modules\Academico\Services\ProgressaoNivelService;
use App\Modules\Academico\UseCases\SugestaoProgressao\CancelarSugestaoProgressaoUseCase;
use App\Modules\Academico\UseCases\SugestaoProgressao\DecidirProgressaoUseCase;
use App\Modules\Academico\UseCases\SugestaoProgressao\SugerirProgressaoUseCase;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class SugestaoProgressaoController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me/niveis',
        summary: 'Níveis da instituição (para o professor escolher o nível sugerido)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        responses: [
            new OA\Response(response: 200, description: 'Níveis ordenados por curso e ordem', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/NivelResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Usuário não é professor desta instituição'),
        ]
    )]
    public function niveis()
    {
        return NivelResource::collection(
            Nivel::query()->orderBy('curso_id')->orderByRaw('ordem IS NULL')->orderBy('ordem')->orderBy('nome')->get()
        );
    }

    #[OA\Post(
        path: '/api/professor/me/progressoes',
        summary: 'Sugerir progressão de nível para um aluno do professor',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreSugestaoProgressaoRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Sugestão registrada (pendente)', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/SugestaoProgressaoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Matrícula de turma ou curso de outro professor'),
            new OA\Response(response: 422, description: 'Matrícula não vigente, nível igual ao atual ou de outro curso, ou sugestão pendente já existente'),
        ]
    )]
    public function store(StoreSugestaoProgressaoRequest $request, SugerirProgressaoUseCase $sugerir)
    {
        $dados = $request->validated();
        $matricula = Matricula::with('turma')->findOrFail($dados['id_matricula']);

        Gate::authorize('create', [SugestaoProgressao::class, $matricula]);

        $sugestao = $sugerir->execute(
            $matricula,
            Nivel::findOrFail($dados['id_nivel_sugerido']),
            $dados['justificativa'],
            $request->user()->professor
        );

        return (new SugestaoProgressaoResource($sugestao->load(SugestaoProgressao::DETALHES)))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Delete(
        path: '/api/professor/me/progressoes/{sugestaoProgressao}',
        summary: 'Cancelar sugestão de progressão ainda pendente (somente o autor)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'sugestaoProgressao', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Sugestão cancelada'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sugestão feita por outro professor'),
            new OA\Response(response: 404, description: 'Sugestão não encontrada'),
            new OA\Response(response: 422, description: 'Sugestão já decidida'),
        ]
    )]
    public function destroy(SugestaoProgressao $sugestaoProgressao, CancelarSugestaoProgressaoUseCase $cancelar)
    {
        Gate::authorize('delete', $sugestaoProgressao);

        $cancelar->execute($sugestaoProgressao);

        return response()->noContent();
    }

    #[OA\Get(
        path: '/api/progressoes',
        summary: 'Sugestões de progressão de nível (fila da secretaria)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'pendente (padrão), aprovada, rejeitada ou todas', schema: new OA\Schema(type: 'string', enum: ['pendente', 'aprovada', 'rejeitada', 'todas'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Nome do aluno', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sugestões paginadas (15 por página)', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SugestaoProgressaoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
            new OA\Response(response: 422, description: 'Filtro inválido'),
        ]
    )]
    public function index(ListSugestoesProgressaoRequest $request, ListSugestoesProgressaoQuery $query, ProgressaoNivelService $progressao)
    {
        Gate::authorize('viewAny', SugestaoProgressao::class);

        $sugestoes = $query->build($request->user(), $request->validated())->paginate(15);

        $sugestoes->getCollection()
            ->filter(fn (SugestaoProgressao $s) => $s->estaPendente())
            ->each(fn (SugestaoProgressao $s) => $s->setRelation('turmasDisponiveis', $progressao->turmasDestino($s)));

        return SugestaoProgressaoResource::collection($sugestoes);
    }

    #[OA\Post(
        path: '/api/progressoes/{sugestaoProgressao}/decisao',
        summary: 'Aprovar ou rejeitar sugestão de progressão (aprovar transfere a matrícula ou troca o nível)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'sugestaoProgressao', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DecidirSugestaoProgressaoRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Sugestão decidida', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/SugestaoProgressaoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel sem acesso'),
            new OA\Response(response: 404, description: 'Sugestão não encontrada'),
            new OA\Response(response: 422, description: 'Sugestão já decidida, turma de destino ausente ou inválida, matrícula não vigente ou motivo da rejeição ausente'),
        ]
    )]
    public function decidir(DecidirSugestaoProgressaoRequest $request, SugestaoProgressao $sugestaoProgressao, DecidirProgressaoUseCase $decidir)
    {
        Gate::authorize('decidir', $sugestaoProgressao);

        $sugestao = $decidir->execute($sugestaoProgressao, $request->validated(), $request->user());

        return new SugestaoProgressaoResource($sugestao->load(SugestaoProgressao::DETALHES));
    }
}
