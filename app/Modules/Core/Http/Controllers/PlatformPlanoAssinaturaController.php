<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\PlanoAssinaturaRepositoryInterface;
use App\Modules\Core\Http\Requests\Platform\StorePlatformPlanoAssinaturaRequest;
use App\Modules\Core\Http\Requests\Platform\UpdatePlatformPlanoAssinaturaRequest;
use App\Modules\Core\Http\Resources\PlatformPlanoAssinaturaResource;
use App\Modules\Core\UseCases\Platform\CreatePlatformPlanoAssinaturaUseCase;
use App\Modules\Core\UseCases\Platform\DeletePlatformPlanoAssinaturaUseCase;
use App\Modules\Core\UseCases\Platform\UpdatePlatformPlanoAssinaturaUseCase;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PlatformPlanoAssinaturaController extends Controller
{
    public function __construct(
        private readonly PlanoAssinaturaRepositoryInterface $planos,
        private readonly CreatePlatformPlanoAssinaturaUseCase $createPlano,
        private readonly UpdatePlatformPlanoAssinaturaUseCase $updatePlano,
        private readonly DeletePlatformPlanoAssinaturaUseCase $deletePlano,
    ) {}

    #[OA\Get(path: '/api/platform/planos', summary: 'Listar planos de assinatura (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request)
    {
        $ativo = $request->has('ativo')
            ? $request->boolean('ativo')
            : null;

        $planos = $this->planos->paginate(
            search: $request->get('search'),
            ativo: $ativo,
            withTrashed: $request->boolean('with_trashed'),
            perPage: (int) $request->get('per_page', 15),
        );

        return PlatformPlanoAssinaturaResource::collection($planos);
    }

    #[OA\Post(
        path: '/api/platform/planos',
        summary: 'Criar plano de assinatura',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StorePlatformPlanoAssinaturaRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/PlatformPlanoAssinaturaResource')),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(StorePlatformPlanoAssinaturaRequest $request)
    {
        $plano = $this->createPlano->execute($request->validated());

        return (new PlatformPlanoAssinaturaResource($plano))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(path: '/api/platform/planos/{id}', summary: 'Exibir plano de assinatura (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(int $plano, Request $request)
    {
        $model = $this->planos->findById(
            $plano,
            withTrashed: $request->boolean('with_trashed'),
        );

        $model->loadCount('instituicoes');

        return new PlatformPlanoAssinaturaResource($model);
    }

    #[OA\Put(path: '/api/platform/planos/{id}', summary: 'Atualizar plano de assinatura (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function update(UpdatePlatformPlanoAssinaturaRequest $request, int $plano)
    {
        $model = $this->planos->findById($plano);
        $updated = $this->updatePlano->execute($model, $request->validated());

        return new PlatformPlanoAssinaturaResource($updated);
    }

    #[OA\Delete(path: '/api/platform/planos/{id}', summary: 'Excluir plano de assinatura (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 204, description: 'No Content')]
    )]
    public function destroy(int $plano)
    {
        $model = $this->planos->findById($plano);
        $this->deletePlano->execute($model);

        return response()->json(null, 204);
    }
}
