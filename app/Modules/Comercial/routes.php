<?php

use App\Modules\Comercial\Http\Controllers\LeadController;
use App\Modules\Comercial\Http\Controllers\PublicLeadController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo Comercial.
| Prefixadas com /api pelo ComercialServiceProvider.
| ResolveInstituicao é aplicado globalmente no grupo api (bootstrap).
*/

Route::post('/leads/public/{slug}', [PublicLeadController::class, 'store']);

Route::middleware(['auth:sanctum', 'ensure.instituicao.membership', 'ensure.plano.modulo:leads'])->group(function () {
    Route::middleware('role:secretaria,admin')->group(function () {
        Route::apiResource('leads', LeadController::class);
    });
});
