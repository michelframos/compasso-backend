<?php

namespace App\Modules\Instrumentos\Providers;

use App\Modules\Instrumentos\Models\Instrumento;
use App\Modules\Instrumentos\Policies\InstrumentoPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(Instrumento::class, InstrumentoPolicy::class);

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
