<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Modules\Notificacoes\Jobs\ProcessarNotificacoesAgendamentos;
use App\Modules\Notificacoes\Jobs\ProcessarNotificacoesContasReceber;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Agendamento diário das notificações WhatsApp (checagem a cada minuto baseada na tabela de config)
Schedule::job(new ProcessarNotificacoesAgendamentos)->everyMinute()->withoutOverlapping();
Schedule::job(new ProcessarNotificacoesContasReceber)->everyMinute()->withoutOverlapping();
