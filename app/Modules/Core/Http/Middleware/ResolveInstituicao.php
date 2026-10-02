<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PersonalAccessToken;
use App\Modules\Core\Support\InstituicaoContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveInstituicao
{
    public function handle(Request $request, Closure $next): Response
    {
        InstituicaoContext::clear();

        $slug = $request->route('slug') ?? $request->header('X-Tenant-Slug');

        if ($slug === null || $slug === '') {
            return $next($request);
        }

        $instituicao = Instituicao::query()
            ->where('slug', $slug)
            ->first();

        if ($instituicao === null) {
            return response()->json(['message' => 'Instituição não encontrada.'], 404);
        }

        if ($instituicao->status !== Instituicao::STATUS_ATIVO && ! $this->isImpersonationRequest($request)) {
            return response()->json(['message' => 'Instituição indisponível.'], 403);
        }

        InstituicaoContext::setFromModel($instituicao);

        $tokenInstituicaoId = $this->resolveTokenInstituicaoId($request);

        if ($tokenInstituicaoId !== null && $tokenInstituicaoId !== $instituicao->id) {
            return response()->json(['message' => 'Token não autorizado para esta instituição.'], 403);
        }

        try {
            return $next($request);
        } finally {
            InstituicaoContext::clear();
        }
    }

    private function resolveTokenInstituicaoId(Request $request): ?int
    {
        $accessToken = $this->resolveAccessToken($request);

        if ($accessToken?->id_instituicao !== null) {
            return (int) $accessToken->id_instituicao;
        }

        return null;
    }

    private function isImpersonationRequest(Request $request): bool
    {
        $token = $this->resolveAccessToken($request);

        return $token !== null && $token->isImpersonating();
    }

    private function resolveAccessToken(Request $request): ?PersonalAccessToken
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken !== null) {
            $accessToken = PersonalAccessToken::findToken($bearerToken);

            if ($accessToken instanceof PersonalAccessToken) {
                return $accessToken;
            }
        }

        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }
}
