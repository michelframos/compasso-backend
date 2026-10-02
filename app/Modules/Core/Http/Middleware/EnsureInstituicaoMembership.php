<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstituicaoMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InstituicaoContext::has()) {
            return response()->json([
                'message' => 'Informe a instituição no header X-Tenant-Slug.',
            ], 422);
        }

        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $pertence = InstituicaoUsuario::query()
            ->where('id_instituicao', InstituicaoContext::id())
            ->where('id_usuario', $user->id)
            ->where('status', InstituicaoUsuario::STATUS_ATIVO)
            ->exists();

        if (! $pertence) {
            return response()->json(['message' => 'Acesso negado para esta instituição.'], 403);
        }

        return $next($request);
    }
}
