<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Instituicao\SwitchInstituicaoRequest;
use App\Modules\Core\Http\Resources\InstituicaoResource;
use App\Modules\Core\UseCases\Instituicao\ListUserInstituicoesUseCase;
use App\Modules\Core\UseCases\Instituicao\SwitchInstituicaoUseCase;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Core', description: 'Instituições do usuário autenticado')]
class InstituicaoController extends Controller
{
    public function __construct(
        private readonly ListUserInstituicoesUseCase $listInstituicoes,
        private readonly SwitchInstituicaoUseCase $switchInstituicao,
    ) {}

    #[OA\Get(
        path: '/api/instituicoes/mine',
        summary: 'Listar instituições do usuário autenticado',
        security: [['sanctum' => []]],
        tags: ['Core'],
        responses: [
            new OA\Response(response: 200, description: 'Lista de instituições'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function mine(Request $request)
    {
        $instituicoes = $this->listInstituicoes->execute($request->user());

        return InstituicaoResource::collection($instituicoes);
    }

    #[OA\Post(
        path: '/api/instituicoes/switch',
        summary: 'Trocar instituição ativa (emite novo token)',
        security: [['sanctum' => []]],
        tags: ['Core'],
        responses: [
            new OA\Response(response: 200, description: 'Token atualizado'),
            new OA\Response(response: 403, description: 'Sem acesso à instituição'),
            new OA\Response(response: 404, description: 'Instituição não encontrada'),
        ]
    )]
    public function switch(SwitchInstituicaoRequest $request)
    {
        $result = $this->switchInstituicao->execute(
            $request->user(),
            $request->validated('tenant_slug'),
            $request->user()->currentAccessToken()
        );

        if ($result === null) {
            $exists = \App\Modules\Core\Models\Instituicao::query()
                ->where('slug', $request->validated('tenant_slug'))
                ->exists();

            if (! $exists) {
                return response()->json(['message' => 'Instituição não encontrada.'], 404);
            }

            return response()->json(['message' => 'Acesso negado para esta instituição.'], 403);
        }

        if (isset($result['subscription_blocked'])) {
            return response()->json($result['details'], 403);
        }

        return response()->json([
            'token' => $result['token'],
            'tenant' => new InstituicaoResource($result['tenant']),
            'tenants' => InstituicaoResource::collection($result['tenants']),
        ]);
    }
}
