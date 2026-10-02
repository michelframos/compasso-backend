<?php

namespace App\Modules\Notificacoes\Providers;

use App\Modules\Notificacoes\Adapters\WhatsappHttpApiAdapter;
use App\Modules\Notificacoes\Contracts\WhatsappGatewayInterface;
use App\Modules\Notificacoes\Services\NotificationChannelResolver;
use App\Modules\Notificacoes\Strategies\EmailNotificationStrategy;
use App\Modules\Notificacoes\Strategies\WhatsappNotificationStrategy;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NotificacoesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsappGatewayInterface::class, fn () => new WhatsappHttpApiAdapter(
            (string) config('services.whatsapp.url', 'http://localhost:8000'),
        ));

        $this->app->tag([
            EmailNotificationStrategy::class,
            WhatsappNotificationStrategy::class,
        ], 'notification.channels');

        $this->app->bind(NotificationChannelResolver::class, fn ($app) => new NotificationChannelResolver(
            $app->tagged('notification.channels'),
        ));
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes.php');
    }
}
