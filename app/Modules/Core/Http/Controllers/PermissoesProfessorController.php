<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Instituicao\UpdatePermissoesProfessorRequest;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\Support\PermissoesProfessorAulas;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PermissoesProfessorController extends Controller
{
    #[OA\Get(
        path: '/api/instituicao/permissoes-professor',
        summary: 'O que os professores podem fazer com as aulas: livre, com aprovação da secretaria ou bloqueado',
        security: [['sanctum' => []]],
        tags: ['Instituição'],
        responses: [
            new OA\Response(response: 200, description: 'Permissões da escola', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/UpdatePermissoesProfessorRequest'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apenas administradores'),
        ]
    )]
    public function show(): JsonResponse
    {
        return response()->json(['data' => PermissoesProfessorAulas::da($this->instituicao())]);
    }

    #[OA\Put(
        path: '/api/instituicao/permissoes-professor',
        summary: 'Define o que os professores podem fazer com as aulas',
        security: [['sanctum' => []]],
        tags: ['Instituição'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdatePermissoesProfessorRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Permissões salvas', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/UpdatePermissoesProfessorRequest'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apenas administradores'),
            new OA\Response(response: 422, description: 'Modo inválido'),
        ]
    )]
    public function update(UpdatePermissoesProfessorRequest $request): JsonResponse
    {
        $instituicao = $this->instituicao();
        $instituicao->update(['permissoes_professor_aulas' => PermissoesProfessorAulas::normalizar($request->validated())]);

        return response()->json(['data' => PermissoesProfessorAulas::da($instituicao)]);
    }

    private function instituicao(): Instituicao
    {
        return Instituicao::query()->findOrFail(InstituicaoContext::id());
    }
}
