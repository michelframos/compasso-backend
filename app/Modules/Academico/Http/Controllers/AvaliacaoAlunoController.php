<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\AvaliacaoAluno\ListAvaliacoesAlunosRequest;
use App\Modules\Academico\Http\Requests\AvaliacaoAluno\StoreAvaliacaoAlunoRequest;
use App\Modules\Academico\Http\Requests\AvaliacaoAluno\UpdateAvaliacaoAlunoRequest;
use App\Modules\Academico\Http\Resources\AvaliacaoAlunoResource;
use App\Modules\Academico\Models\AvaliacaoAluno;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\UseCases\AvaliacaoAluno\AtualizarAvaliacaoUseCase;
use App\Modules\Academico\UseCases\AvaliacaoAluno\ExcluirAvaliacaoUseCase;
use App\Modules\Academico\UseCases\AvaliacaoAluno\RegistrarAvaliacaoUseCase;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class AvaliacaoAlunoController extends Controller
{
    #[OA\Get(
        path: '/api/professor/me/avaliacoes',
        summary: 'Avaliações de uma turma do professor ou de um aluno seu (inclui as registradas por colegas)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'id_turma', in: 'query', required: false, description: 'Obrigatório sem id_aluno', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'id_aluno', in: 'query', required: false, description: 'Obrigatório sem id_turma', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Avaliações, da mais recente para a mais antiga', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AvaliacaoAlunoResource')),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Turma de outro professor ou aluno sem matrícula vigente com o professor'),
            new OA\Response(response: 422, description: 'Filtro ausente ou inválido'),
        ]
    )]
    public function index(ListAvaliacoesAlunosRequest $request)
    {
        $filtros = $request->validated();

        if (! empty($filtros['id_turma'])) {
            Gate::authorize('view', Turma::findOrFail($filtros['id_turma']));
        }
        if (! empty($filtros['id_aluno'])) {
            Gate::authorize('verFichaAluno', Aluno::findOrFail($filtros['id_aluno']));
        }

        $avaliacoes = AvaliacaoAluno::query()
            ->visivelPara($request->user())
            ->when($filtros['id_turma'] ?? null, fn ($q, $idTurma) => $q->where('id_turma', $idTurma))
            ->when($filtros['id_aluno'] ?? null, fn ($q, $idAluno) => $q->where('id_aluno', $idAluno))
            ->with(AvaliacaoAluno::DETALHES)
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        return AvaliacaoAlunoResource::collection($avaliacoes);
    }

    #[OA\Post(
        path: '/api/professor/me/avaliacoes',
        summary: 'Registrar avaliação de um aluno do professor',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreAvaliacaoAlunoRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Avaliação registrada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AvaliacaoAlunoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Aluno sem matrícula vigente com o professor (ou na turma informada)'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(StoreAvaliacaoAlunoRequest $request, RegistrarAvaliacaoUseCase $registrar)
    {
        $dados = $request->validated();
        $turma = isset($dados['id_turma']) ? Turma::findOrFail($dados['id_turma']) : null;

        Gate::authorize('create', [AvaliacaoAluno::class, Aluno::findOrFail($dados['id_aluno']), $turma]);

        $avaliacao = $registrar->execute($dados, $request->user()->professor);

        return new AvaliacaoAlunoResource($avaliacao->load(AvaliacaoAluno::DETALHES));
    }

    #[OA\Put(
        path: '/api/professor/me/avaliacoes/{avaliacaoAluno}',
        summary: 'Editar avaliação (somente o autor)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'avaliacaoAluno', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateAvaliacaoAlunoRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Avaliação atualizada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/AvaliacaoAlunoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Avaliação registrada por outro professor'),
            new OA\Response(response: 404, description: 'Avaliação não encontrada'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(UpdateAvaliacaoAlunoRequest $request, AvaliacaoAluno $avaliacaoAluno, AtualizarAvaliacaoUseCase $atualizar)
    {
        Gate::authorize('update', $avaliacaoAluno);

        $avaliacao = $atualizar->execute($avaliacaoAluno, $request->validated());

        return new AvaliacaoAlunoResource($avaliacao->load(AvaliacaoAluno::DETALHES));
    }

    #[OA\Delete(
        path: '/api/professor/me/avaliacoes/{avaliacaoAluno}',
        summary: 'Excluir avaliação (somente o autor)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'avaliacaoAluno', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Avaliação excluída'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Avaliação registrada por outro professor'),
            new OA\Response(response: 404, description: 'Avaliação não encontrada'),
        ]
    )]
    public function destroy(AvaliacaoAluno $avaliacaoAluno, ExcluirAvaliacaoUseCase $excluir)
    {
        Gate::authorize('delete', $avaliacaoAluno);

        $excluir->execute($avaliacaoAluno);

        return response()->noContent();
    }
}
