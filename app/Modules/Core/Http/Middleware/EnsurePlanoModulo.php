<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Support\InstituicaoContext;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanoModulo
{
    public function __construct(
        private readonly PlanoEntitlementResolverInterface $entitlements,
    ) {}

    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        $instituicao = InstituicaoContext::instituicao();

        if ($instituicao === null) {
            return response()->json([
                'message' => 'Informe a instituição no header X-Tenant-Slug.',
            ], 422);
        }

        if (! $this->entitlements->permiteModulo($instituicao, $modulo)) {
            return response()->json([
                'message' => 'Este módulo não está incluído no plano da escola.',
                'code' => 'modulo_nao_incluido',
                'modulo' => $modulo,
            ], 403);
        }

        return $next($request);
    }
}
