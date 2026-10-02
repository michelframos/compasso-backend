<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Platform\ImpersonatePlatformInstituicaoUserRequest;
use App\Modules\Core\Http\Resources\PlatformInstituicaoUsuarioResource;
use App\Modules\Core\UseCases\Platform\ImpersonateInstituicaoUserUseCase;
use App\Modules\Core\UseCases\Platform\ListPlatformInstituicaoUsuariosUseCase;
use App\Modules\Core\UseCases\Platform\StopPlatformImpersonationUseCase;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PlatformImpersonationController extends Controller
{
    public function __construct(
        private readonly StopPlatformImpersonationUseCase $stopImpersonation,
    ) {}

    #[OA\Post(
        path: '/api/platform/stop-impersonation',
        summary: 'Encerrar impersonação e restaurar sessão super-admin',
        description: 'Chamar com o token de impersonação ativo (Bearer). Retorna novo token do super-admin sem tenant.',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sessão platform restaurada',
                content: new OA\JsonContent(ref: '#/components/schemas/StopImpersonationResponse')
            ),
            new OA\Response(response: 422, description: 'Token não é de impersonação'),
        ]
    )]
    public function stop(Request $request)
    {
        $result = $this->stopImpersonation->execute(
            $request->user(),
            $request,
        );

        return response()->json($result);
    }
}
