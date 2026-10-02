<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Modules\Core\Http\Middleware\ResolveInstituicao::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'resolve.instituicao' => \App\Modules\Core\Http\Middleware\ResolveInstituicao::class,
            'ensure.instituicao.membership' => \App\Modules\Core\Http\Middleware\EnsureInstituicaoMembership::class,
            'ensure.plano.modulo' => \App\Modules\Core\Http\Middleware\EnsurePlanoModulo::class,
            'super_admin' => \App\Modules\Core\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'não foi possível concluir a solicitação'
                ], 404);
            }
        });
    })->create();
