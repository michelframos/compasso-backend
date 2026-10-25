<?php

namespace App\Modules\Academico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academico\Http\Requests\ObservacaoAluno\StoreObservacaoAlunoRequest;
use App\Modules\Academico\Http\Requests\ObservacaoAluno\UpdateObservacaoAlunoRequest;
use App\Modules\Academico\Http\Resources\ObservacaoAlunoResource;
use App\Modules\Academico\Models\ObservacaoAluno;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\UseCases\ObservacaoAluno\AtualizarObservacaoAlunoUseCase;
use App\Modules\Academico\UseCases\ObservacaoAluno\CriarObservacaoAlunoUseCase;
use App\Modules\Academico\UseCases\ObservacaoAluno\ExcluirObservacaoAlunoUseCase;
use App\Modules\Pessoas\Models\Aluno;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class ObservacaoAlunoController extends Controller
{
    #[OA\Post(
        path: '/api/professor/me/observacoes',
        summary: 'Registrar observação pedagógica sobre um aluno das turmas do professor',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StoreObservacaoAlunoRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Observação registrada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ObservacaoAlunoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Aluno não matriculado nas turmas do professor (ou na turma informada)'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(StoreObservacaoAlunoRequest $request, CriarObservacaoAlunoUseCase $criar)
    {
        $dados = $request->validated();
        $turma = isset($dados['id_turma']) ? Turma::findOrFail($dados['id_turma']) : null;

        Gate::authorize('create', [ObservacaoAluno::class, Aluno::findOrFail($dados['id_aluno']), $turma]);

        $observacao = $criar->execute($dados, $request->user()->professor);

        return (new ObservacaoAlunoResource($observacao->load(ObservacaoAluno::DETALHES)))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Put(
        path: '/api/professor/me/observacoes/{observacaoAluno}',
        summary: 'Editar observação pedagógica (somente o autor)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'observacaoAluno', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateObservacaoAlunoRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Observação atualizada', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ObservacaoAlunoResource'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Observação registrada por outro professor'),
            new OA\Response(response: 404, description: 'Observação não encontrada'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function update(UpdateObservacaoAlunoRequest $request, ObservacaoAluno $observacaoAluno, AtualizarObservacaoAlunoUseCase $atualizar)
    {
        Gate::authorize('update', $observacaoAluno);

        $observacao = $atualizar->execute($observacaoAluno, $request->validated());

        return new ObservacaoAlunoResource($observacao->load(ObservacaoAluno::DETALHES));
    }

    #[OA\Delete(
        path: '/api/professor/me/observacoes/{observacaoAluno}',
        summary: 'Excluir observação pedagógica (somente o autor)',
        security: [['sanctum' => []]],
        tags: ['Academico'],
        parameters: [
            new OA\Parameter(name: 'observacaoAluno', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Observação excluída'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Observação registrada por outro professor'),
            new OA\Response(response: 404, description: 'Observação não encontrada'),
        ]
    )]
    public function destroy(ObservacaoAluno $observacaoAluno, ExcluirObservacaoAlunoUseCase $excluir)
    {
        Gate::authorize('delete', $observacaoAluno);

        $excluir->execute($observacaoAluno);

        return response()->noContent();
    }
}
