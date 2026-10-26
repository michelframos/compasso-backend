<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\JustificativaFalta\ListJustificativasRequest;
use App\Modules\Academico\Http\Requests\JustificativaFalta\RegistrarJustificativaRequest;
use App\Modules\Academico\Http\Resources\JustificativaDoAlunoResource;
use App\Modules\Academico\Models\JustificativaFalta;
use App\Modules\Academico\UseCases\JustificativaFalta\RegistrarJustificativaUseCase;
use App\Modules\Core\Contracts\AlunoAlvoResolverPort;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class AlunoJustificativaController extends Controller
{
    public function __construct(private readonly AlunoAlvoResolverPort $alunos) {}

    #[OA\Get(
        path: '/api/aluno/me/justificativas',
        summary: 'Avisos de ausência e justificativas do aluno',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório para o responsável', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedidos, do mais recente ao mais antigo', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/JustificativaDoAlunoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Papel indevido, sem vínculo ou outra instituição'),
            new OA\Response(response: 422, description: 'Responsável sem id_aluno'),
        ]
    )]
    public function index(ListJustificativasRequest $request)
    {
        $aluno = $this->aluno($request);
        Gate::authorize('viewAnyDoAluno', [JustificativaFalta::class, $aluno->id]);

        $pedidos = JustificativaFalta::query()
            ->where('id_aluno', $aluno->id)
            ->orderByDesc('id')
            ->get();

        return JustificativaDoAlunoResource::collection($pedidos);
    }

    #[OA\Post(
        path: '/api/aluno/me/justificativas',
        summary: 'Avisar ausência de uma aula futura ou justificar uma falta registrada',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(ref: '#/components/schemas/RegistrarJustificativaRequest')
        )),
        responses: [
            new OA\Response(response: 201, description: 'Pedido registrado', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/JustificativaDoAlunoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Aula de outro aluno ou sem vínculo'),
            new OA\Response(response: 404, description: 'Aula não encontrada'),
            new OA\Response(response: 422, description: 'Aula cancelada, tipo inadequado ou pedido já existente'),
        ]
    )]
    public function store(RegistrarJustificativaRequest $request, RegistrarJustificativaUseCase $registrar)
    {
        $idInformado = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;
        $aluno = $this->alunos->resolver($request->user(), $idInformado);
        Gate::authorize('viewAnyDoAluno', [JustificativaFalta::class, $aluno->id]);

        $justificativa = $registrar->execute($aluno->id, $request->safe()->except(['id_aluno', 'anexo']), $request->file('anexo'));

        return (new JustificativaDoAlunoResource($justificativa))->response()->setStatusCode(201);
    }

    private function aluno(ListJustificativasRequest $request)
    {
        $idAluno = $request->filled('id_aluno') ? $request->integer('id_aluno') : null;

        return $this->alunos->resolver($request->user(), $idAluno);
    }
}
