<?php

use App\Modules\Instrumentos\Http\Controllers\InstrumentoController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo Instrumentos.
| Prefixadas com /api pelo InstrumentosServiceProvider.
| ResolveInstituicao é aplicado globalmente no grupo api (bootstrap).
*/

Route::middleware(['auth:sanctum', 'ensure.instituicao.membership', 'ensure.plano.modulo:instrumentos'])->group(function () {
    Route::middleware('role:secretaria,admin')->group(function () {
        Route::apiResource('instrumentos', InstrumentoController::class);
        Route::post('instrumentos/{instrumento}/emprestar', [InstrumentoController::class, 'emprestar']);
        Route::post('instrumentos/{instrumento}/devolver', [InstrumentoController::class, 'devolver']);
        Route::get('instrumentos/{instrumento}/historico', [InstrumentoController::class, 'historico']);
    });
});
