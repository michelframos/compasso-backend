<?php

namespace App\Modules\Relatorios\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RelatoriosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Relatórios são somente leitura; acoplamento via App\Models aliases é aceito.
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
