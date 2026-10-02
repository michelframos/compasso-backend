<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\DepoimentoRepositoryInterface;
use App\Modules\Core\Http\Requests\Platform\StorePlatformDepoimentoRequest;
use App\Modules\Core\Http\Requests\Platform\UpdatePlatformDepoimentoRequest;
use App\Modules\Core\Http\Resources\PlatformDepoimentoResource;
use App\Modules\Core\UseCases\Platform\AprovarDepoimentoUseCase;
use App\Modules\Core\UseCases\Platform\CreatePlatformDepoimentoUseCase;
use App\Modules\Core\UseCases\Platform\DeletePlatformDepoimentoUseCase;
use App\Modules\Core\UseCases\Platform\OcultarDepoimentoUseCase;
use App\Modules\Core\UseCases\Platform\UpdatePlatformDepoimentoUseCase;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PlatformDepoimentoController extends Controller
{
    public function __construct(
        private readonly DepoimentoRepositoryInterface $depoimentos,
        private readonly CreatePlatformDepoimentoUseCase $createDepoimento,
        private readonly UpdatePlatformDepoimentoUseCase $updateDepoimento,
        private readonly DeletePlatformDepoimentoUseCase $deleteDepoimento,
        private readonly AprovarDepoimentoUseCase $aprovarDepoimento,
        private readonly OcultarDepoimentoUseCase $ocultarDepoimento,
    ) {}

    #[OA\Get(path: '/api/platform/depoimentos', summary: 'Listar depoimentos (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request)
    {
        $aprovado = $request->has('aprovado')
            ? $request->boolean('aprovado')
            : null;

        $depoimentos = $this->depoimentos->paginate(
            search: $request->get('search'),
            aprovado: $aprovado,
            withTrashed: $request->boolean('with_trashed'),
            perPage: (int) $request->get('per_page', 15),
        );

        return PlatformDepoimentoResource::collection($depoimentos);
    }

    #[OA\Post(
        path: '/api/platform/depoimentos',
        summary: 'Criar depoimento (sempre pendente)',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StorePlatformDepoimentoRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ]
    )]
    public function store(StorePlatformDepoimentoRequest $request)
    {
        $depoimento = $this->createDepoimento->execute($request->validated());

        return (new PlatformDepoimentoResource($depoimento))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(path: '/api/platform/depoimentos/{id}', summary: 'Exibir depoimento (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(int $depoimento)
    {
        $model = $this->depoimentos->findById($depoimento);

        return new PlatformDepoimentoResource($model);
    }

    #[OA\Put(path: '/api/platform/depoimentos/{id}', summary: 'Atualizar depoimento (não publica)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function update(UpdatePlatformDepoimentoRequest $request, int $depoimento)
    {
        $model = $this->depoimentos->findById($depoimento);
        $updated = $this->updateDepoimento->execute($model, $request->validated());

        return new PlatformDepoimentoResource($updated);
    }

    #[OA\Delete(path: '/api/platform/depoimentos/{id}', summary: 'Excluir depoimento', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 204, description: 'No Content')]
    )]
    public function destroy(int $depoimento)
    {
        $model = $this->depoimentos->findById($depoimento);
        $this->deleteDepoimento->execute($model);

        return response()->json(null, 204);
    }

    #[OA\Post(path: '/api/platform/depoimentos/{id}/aprovar', summary: 'Aprovar depoimento para o site', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function aprovar(int $depoimento)
    {
        $model = $this->depoimentos->findById($depoimento);
        $updated = $this->aprovarDepoimento->execute($model);

        return new PlatformDepoimentoResource($updated);
    }

    #[OA\Post(path: '/api/platform/depoimentos/{id}/ocultar', summary: 'Ocultar depoimento do site', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function ocultar(int $depoimento)
    {
        $model = $this->depoimentos->findById($depoimento);
        $updated = $this->ocultarDepoimento->execute($model);

        return new PlatformDepoimentoResource($updated);
    }
}
