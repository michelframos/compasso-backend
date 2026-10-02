<?php

namespace App\Modules\Espetaculos\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EspetaculosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Figurino → FinanceiroServiceProvider (CriarCobrancasFigurinoPort)
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
