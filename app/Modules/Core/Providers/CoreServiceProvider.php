<?php

namespace App\Modules\Core\Providers;

use App\Models\User as LegacyUserAlias;
use App\Modules\Core\Contracts\DepoimentoRepositoryInterface;
use App\Modules\Core\Contracts\InstituicaoRepositoryInterface;
use App\Modules\Core\Contracts\PlanoAssinaturaRepositoryInterface;
use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Contracts\SiteModuloRepositoryInterface;
use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Core\Models\ConfiguracaoEmpresa;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\PlanoAssinatura;
use App\Modules\Core\Observers\InstituicaoEntitlementObserver;
use App\Modules\Core\Observers\PlanoAssinaturaEntitlementObserver;
use App\Modules\Core\Observers\UserObserver;
use App\Modules\Core\Repositories\DepoimentoRepository;
use App\Modules\Core\Repositories\InstituicaoRepository;
use App\Modules\Core\Repositories\PlanoAssinaturaRepository;
use App\Modules\Core\Repositories\SiteModuloRepository;
use App\Modules\Core\Repositories\UserRepository;
use App\Modules\Core\Support\CachedPlanoEntitlementResolver;
use App\Modules\Core\Support\PlanoEntitlementResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use App\Modules\Core\Models\PersonalAccessToken;

/**
 * Fundação compartilhada multi-tenant + Identity/Config (Fase 3).
 */
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(InstituicaoRepositoryInterface::class, InstituicaoRepository::class);
        $this->app->bind(PlanoAssinaturaRepositoryInterface::class, PlanoAssinaturaRepository::class);
        $this->app->bind(DepoimentoRepositoryInterface::class, DepoimentoRepository::class);
        $this->app->bind(SiteModuloRepositoryInterface::class, SiteModuloRepository::class);

        $this->app->when(CachedPlanoEntitlementResolver::class)
            ->needs(PlanoEntitlementResolverInterface::class)
            ->give(PlanoEntitlementResolver::class);
        $this->app->singleton(CachedPlanoEntitlementResolver::class);
        $this->app->alias(CachedPlanoEntitlementResolver::class, PlanoEntitlementResolverInterface::class);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Alias App\Models\User ainda é o Authenticatable usado pela app/legado.
        LegacyUserAlias::observe(UserObserver::class);

        Instituicao::observe(InstituicaoEntitlementObserver::class);
        ConfiguracaoEmpresa::observe(InstituicaoEntitlementObserver::class);
        PlanoAssinatura::observe(PlanoAssinaturaEntitlementObserver::class);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
