<?php

namespace App\Modules\Bilheteria\Providers;

use App\Modules\Bilheteria\Models\CupomIngresso;
use App\Modules\Bilheteria\Models\Ingresso;
use App\Modules\Bilheteria\Models\TemplateIngresso;
use App\Modules\Bilheteria\Policies\CupomIngressoPolicy;
use App\Modules\Bilheteria\Policies\IngressoPolicy;
use App\Modules\Bilheteria\Policies\TemplateIngressoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BilheteriaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(TemplateIngresso::class, TemplateIngressoPolicy::class);
        Gate::policy(Ingresso::class, IngressoPolicy::class);
        Gate::policy(CupomIngresso::class, CupomIngressoPolicy::class);

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
