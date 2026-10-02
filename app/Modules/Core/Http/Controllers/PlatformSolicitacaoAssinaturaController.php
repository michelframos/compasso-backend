<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Platform\RejeitarSolicitacaoAssinaturaRequest;
use App\Modules\Core\Http\Resources\PlatformSolicitacaoAssinaturaResource;
use App\Modules\Core\Models\SolicitacaoAssinatura;
use App\Modules\Core\UseCases\Platform\AprovarSolicitacaoAssinaturaUseCase;
use App\Modules\Core\UseCases\Platform\RejeitarSolicitacaoAssinaturaUseCase;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PlatformSolicitacaoAssinaturaController extends Controller
{
    public function __construct(
        private readonly AprovarSolicitacaoAssinaturaUseCase $aprovar,
        private readonly RejeitarSolicitacaoAssinaturaUseCase $rejeitar,
    ) {}

    #[OA\Get(
        path: '/api/platform/solicitacoes',
        summary: 'Lista solicitações de assinatura',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request)
    {
        $query = SolicitacaoAssinatura::query()
            ->with(['plano', 'instituicao', 'usuario', 'aprovadoPor'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->whereHas('instituicao', function ($q) use ($search) {
                $q->where('nome_fantasia', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 50);

        return PlatformSolicitacaoAssinaturaResource::collection(
            $query->paginate($perPage)
        );
    }

    #[OA\Post(
        path: '/api/platform/solicitacoes/{solicitacao}/aprovar',
        summary: 'Aprova solicitação e ativa plano na instituição',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function aprovar(SolicitacaoAssinatura $solicitacao)
    {
        $result = $this->aprovar->execute($solicitacao, request()->user());

        return new PlatformSolicitacaoAssinaturaResource($result);
    }

    #[OA\Post(
        path: '/api/platform/solicitacoes/{solicitacao}/rejeitar',
        summary: 'Rejeita solicitação de assinatura',
        security: [['sanctum' => []]],
        tags: ['Platform'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function rejeitar(RejeitarSolicitacaoAssinaturaRequest $request, SolicitacaoAssinatura $solicitacao)
    {
        $result = $this->rejeitar->execute(
            $solicitacao,
            $request->user(),
            $request->validated('observacao'),
        );

        return new PlatformSolicitacaoAssinaturaResource($result);
    }
}
