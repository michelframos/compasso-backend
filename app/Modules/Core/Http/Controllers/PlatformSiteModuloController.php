<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Contracts\SiteModuloRepositoryInterface;
use App\Modules\Core\Http\Requests\Platform\StorePlatformSiteModuloRequest;
use App\Modules\Core\Http\Requests\Platform\UpdatePlatformSiteModuloRequest;
use App\Modules\Core\Http\Resources\PlatformSiteModuloResource;
use App\Modules\Core\UseCases\Platform\AprovarSiteModuloUseCase;
use App\Modules\Core\UseCases\Platform\CreatePlatformSiteModuloUseCase;
use App\Modules\Core\UseCases\Platform\DeletePlatformSiteModuloUseCase;
use App\Modules\Core\UseCases\Platform\OcultarSiteModuloUseCase;
use App\Modules\Core\UseCases\Platform\UpdatePlatformSiteModuloUseCase;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PlatformSiteModuloController extends Controller
{
    public function __construct(
        private readonly SiteModuloRepositoryInterface $modulos,
        private readonly CreatePlatformSiteModuloUseCase $createModulo,
        private readonly UpdatePlatformSiteModuloUseCase $updateModulo,
        private readonly DeletePlatformSiteModuloUseCase $deleteModulo,
        private readonly AprovarSiteModuloUseCase $aprovarModulo,
        private readonly OcultarSiteModuloUseCase $ocultarModulo,
    ) {}

    #[OA\Get(path: '/api/platform/modulos', summary: 'Listar módulos do site (platform)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request)
    {
        $aprovado = $request->has('aprovado')
            ? $request->boolean('aprovado')
            : null;

        $modulos = $this->modulos->paginate(
            search: $request->get('search'),
            aprovado: $aprovado,
            withTrashed: $request->boolean('with_trashed'),
            perPage: (int) $request->get('per_page', 15),
        );

        return PlatformSiteModuloResource::collection($modulos);
    }

    #[OA\Post(path: '/api/platform/modulos', summary: 'Criar módulo do site (sempre pendente)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(StorePlatformSiteModuloRequest $request)
    {
        $modulo = $this->createModulo->execute($request->validated());

        return (new PlatformSiteModuloResource($modulo))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(path: '/api/platform/modulos/{id}', summary: 'Exibir módulo do site', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(int $modulo)
    {
        return new PlatformSiteModuloResource($this->modulos->findById($modulo));
    }

    #[OA\Put(path: '/api/platform/modulos/{id}', summary: 'Atualizar módulo do site (não publica)', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function update(UpdatePlatformSiteModuloRequest $request, int $modulo)
    {
        $model = $this->modulos->findById($modulo);
        $updated = $this->updateModulo->execute($model, $request->validated());

        return new PlatformSiteModuloResource($updated);
    }

    #[OA\Delete(path: '/api/platform/modulos/{id}', summary: 'Excluir módulo do site', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 204, description: 'No Content')]
    )]
    public function destroy(int $modulo)
    {
        $model = $this->modulos->findById($modulo);
        $this->deleteModulo->execute($model);

        return response()->json(null, 204);
    }

    #[OA\Post(path: '/api/platform/modulos/{id}/aprovar', summary: 'Aprovar módulo para o site', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function aprovar(int $modulo)
    {
        $model = $this->modulos->findById($modulo);

        return new PlatformSiteModuloResource($this->aprovarModulo->execute($model));
    }

    #[OA\Post(path: '/api/platform/modulos/{id}/ocultar', summary: 'Ocultar módulo do site', security: [['sanctum' => []]], tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function ocultar(int $modulo)
    {
        $model = $this->modulos->findById($modulo);

        return new PlatformSiteModuloResource($this->ocultarModulo->execute($model));
    }
}
