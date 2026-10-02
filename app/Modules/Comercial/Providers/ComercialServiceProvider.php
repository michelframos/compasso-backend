<?php

namespace App\Modules\Comercial\Providers;

use App\Modules\Comercial\Adapters\MarcarLeadMatriculadoAdapter;
use App\Modules\Core\Contracts\MarcarLeadMatriculadoPort;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ComercialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MarcarLeadMatriculadoPort::class, MarcarLeadMatriculadoAdapter::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
