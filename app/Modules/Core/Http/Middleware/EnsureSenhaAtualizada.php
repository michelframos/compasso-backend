<?php

namespace App\Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSenhaAtualizada
{
    public const CODIGO = 'troca_senha_obrigatoria';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        if ($user->deve_trocar_senha) {
            return response()->json([
                'message' => 'Você precisa definir uma nova senha antes de continuar.',
                'code' => self::CODIGO,
            ], 403);
        }

        return $next($request);
    }
}
