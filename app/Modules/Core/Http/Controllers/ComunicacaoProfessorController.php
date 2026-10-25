<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Instituicao\UpdateComunicacaoProfessorRequest;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\Support\LimiteAvisosProfessor;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ComunicacaoProfessorController extends Controller
{
    #[OA\Get(
        path: '/api/instituicao/comunicacao-professor',
        summary: 'Limite diário de avisos que cada professor pode enviar a alunos e responsáveis',
        security: [['sanctum' => []]],
        tags: ['Instituição'],
        responses: [
            new OA\Response(response: 200, description: 'Configuração da escola (padrão: 10 por dia)', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/UpdateComunicacaoProfessorRequest'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apenas administradores'),
        ]
    )]
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->dados($this->instituicao())]);
    }

    #[OA\Put(
        path: '/api/instituicao/comunicacao-professor',
        summary: 'Define o limite diário de avisos por professor',
        security: [['sanctum' => []]],
        tags: ['Instituição'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateComunicacaoProfessorRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Configuração salva', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/UpdateComunicacaoProfessorRequest'),
            ])),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Apenas administradores'),
            new OA\Response(response: 422, description: 'Limite fora do intervalo permitido'),
        ]
    )]
    public function update(UpdateComunicacaoProfessorRequest $request): JsonResponse
    {
        $instituicao = $this->instituicao();
        $instituicao->update(['limite_avisos_professor_dia' => $request->validated('limite_avisos_dia')]);

        return response()->json(['data' => $this->dados($instituicao)]);
    }

    private function dados(Instituicao $instituicao): array
    {
        return ['limite_avisos_dia' => LimiteAvisosProfessor::da($instituicao)];
    }

    private function instituicao(): Instituicao
    {
        return Instituicao::query()->findOrFail(InstituicaoContext::id());
    }
}
