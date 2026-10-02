<?php

namespace App\Modules\Academico\Providers;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\MaterialTurma;
use App\Modules\Academico\Models\Matricula;
use App\Modules\Academico\Models\Turma;
use App\Modules\Academico\Policies\AulaTurmaPolicy;
use App\Modules\Academico\Policies\MaterialTurmaPolicy;
use App\Modules\Academico\Policies\MatriculaPolicy;
use App\Modules\Academico\Policies\TurmaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AcademicoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // MarcarLead → ComercialServiceProvider (Fase 7)
        // Mensalidades/conta-aula → FinanceiroServiceProvider (Fase 6)
    }

    public function boot(): void
    {
        Gate::policy(Matricula::class, MatriculaPolicy::class);
        Gate::policy(Turma::class, TurmaPolicy::class);
        Gate::policy(AulaTurma::class, AulaTurmaPolicy::class);
        Gate::policy(MaterialTurma::class, MaterialTurmaPolicy::class);

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
