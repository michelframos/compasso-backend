<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Core\Providers\CoreServiceProvider::class,
    // Módulos
    App\Modules\Pessoas\Providers\PessoasServiceProvider::class,
    App\Modules\Academico\Providers\AcademicoServiceProvider::class,
    App\Modules\Financeiro\Providers\FinanceiroServiceProvider::class,
    App\Modules\Comercial\Providers\ComercialServiceProvider::class,
    App\Modules\Espetaculos\Providers\EspetaculosServiceProvider::class,
    App\Modules\Instrumentos\Providers\InstrumentosServiceProvider::class,
    App\Modules\Notificacoes\Providers\NotificacoesServiceProvider::class,
    App\Modules\Relatorios\Providers\RelatoriosServiceProvider::class,
];
