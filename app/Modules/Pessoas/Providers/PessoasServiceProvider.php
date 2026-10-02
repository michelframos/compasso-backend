<?php

namespace App\Modules\Pessoas\Providers;

use App\Modules\Pessoas\Models\MedidaAluno;
use App\Modules\Pessoas\Models\Responsavel;
use App\Modules\Pessoas\Policies\MedidaAlunoPolicy;
use App\Modules\Pessoas\Policies\ResponsavelPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PessoasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(MedidaAluno::class, MedidaAlunoPolicy::class);
        Gate::policy(Responsavel::class, ResponsavelPolicy::class);

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
