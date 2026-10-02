<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // UserObserver registrado em App\Modules\Core\Providers\CoreServiceProvider

        // Mesmo comportamento do middleware CheckRole: admin tem acesso a tudo.
        Gate::before(fn ($user) => $user?->role === 'admin' ? true : null);
    }
}
