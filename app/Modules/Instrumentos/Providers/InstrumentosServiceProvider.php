<?php

namespace App\Modules\Instrumentos\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class InstrumentosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Conta de empréstimo → FinanceiroServiceProvider (CriarContaEmprestimoInstrumentoPort)
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
