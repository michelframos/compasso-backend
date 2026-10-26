<?php

namespace App\Modules\Pessoas\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige cadastro de aluno ou de responsável na instituição ativa.
 * O middleware `role` libera administrador; este não.
 */
class EnsurePerfilAluno
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $ehAluno = $user->role === 'aluno' && $user->aluno !== null;
        $ehResponsavel = $user->role === 'responsavel' && $user->responsavel !== null;

        if (! $ehAluno && ! $ehResponsavel) {
            return response()->json(['message' => 'Acesso restrito à área do aluno.'], 403);
        }

        return $next($request);
    }
}
