<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $user = Auth::user();

        // Logs removidos para evitar poluição

        // Se o usuário for admin, pode acessar tudo
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Verifica se a role do usuário está na lista permitida
        if (!in_array($user->role, $roles)) {
            return response()->json(['message' => 'Acesso negado para o seu perfil.'], 403);
        }

        return $next($request);
    }
}
