<?php

namespace App\Modules\Pessoas\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige um cadastro de professor do usuário na instituição ativa.
 * Diferente do `role:professor`, não libera administradores sem esse cadastro.
 */
class EnsurePerfilProfessor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        if ($user->role !== 'professor' || $user->professor === null) {
            return response()->json(['message' => 'Acesso restrito à área do professor.'], 403);
        }

        return $next($request);
    }
}
