<?php

namespace App\Modules\Espetaculos\Providers;

use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\ApresentacaoAluno;
use App\Modules\Espetaculos\Models\Ensaio;
use App\Modules\Espetaculos\Models\Espetaculo;
use App\Modules\Espetaculos\Policies\ApresentacaoAlunoPolicy;
use App\Modules\Espetaculos\Policies\ApresentacaoPolicy;
use App\Modules\Espetaculos\Policies\EnsaioPolicy;
use App\Modules\Espetaculos\Policies\EspetaculoPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(Espetaculo::class, EspetaculoPolicy::class);
        Gate::policy(Apresentacao::class, ApresentacaoPolicy::class);
        Gate::policy(ApresentacaoAluno::class, ApresentacaoAlunoPolicy::class);
        Gate::policy(Ensaio::class, EnsaioPolicy::class);

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
