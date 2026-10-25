<?php

namespace App\Modules\Financeiro\Providers;

use App\Modules\Core\Contracts\CobrancaAulaPort;
use App\Modules\Core\Contracts\CriarCobrancasFigurinoPort;
use App\Modules\Core\Contracts\CriarContaAulaPort;
use App\Modules\Core\Contracts\CriarContaEmprestimoInstrumentoPort;
use App\Modules\Core\Contracts\GerarMensalidadesPort;
use App\Modules\Core\Contracts\GerarPagamentoProfessorPort;
use App\Modules\Financeiro\Adapters\CobrancaAulaAdapter;
use App\Modules\Financeiro\Adapters\CriarCobrancasFigurinoAdapter;
use App\Modules\Financeiro\Adapters\CriarContaAulaAdapter;
use App\Modules\Financeiro\Adapters\CriarContaEmprestimoInstrumentoAdapter;
use App\Modules\Financeiro\Adapters\GerarMensalidadesAdapter;
use App\Modules\Financeiro\Adapters\GerarPagamentoProfessorAdapter;
use App\Modules\Financeiro\Models\Conta;
use App\Modules\Financeiro\Policies\ContaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FinanceiroServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GerarMensalidadesPort::class, GerarMensalidadesAdapter::class);
        $this->app->bind(CriarContaAulaPort::class, CriarContaAulaAdapter::class);
        $this->app->bind(CriarCobrancasFigurinoPort::class, CriarCobrancasFigurinoAdapter::class);
        $this->app->bind(CriarContaEmprestimoInstrumentoPort::class, CriarContaEmprestimoInstrumentoAdapter::class);
        $this->app->bind(GerarPagamentoProfessorPort::class, GerarPagamentoProfessorAdapter::class);
        $this->app->bind(CobrancaAulaPort::class, CobrancaAulaAdapter::class);
    }

    public function boot(): void
    {
        Gate::policy(Conta::class, ContaPolicy::class);

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
