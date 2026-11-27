<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Modules\Core\Jobs\SyncAssinaturasGatewayJob;
use App\Modules\Financeiro\Jobs\SyncCobrancasContaGatewayJob;
use App\Modules\Notificacoes\Jobs\ProcessarNotificacoesAgendamentos;
use App\Modules\Notificacoes\Jobs\ProcessarNotificacoesContasReceber;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Agendamento diário das notificações WhatsApp (checagem a cada minuto baseada na tabela de config)
Schedule::job(new ProcessarNotificacoesAgendamentos)->everyMinute()->withoutOverlapping();
Schedule::job(new ProcessarNotificacoesContasReceber)->everyMinute()->withoutOverlapping();

// Polling do centralizador de pagamentos (assinaturas SaaS e subcontas das escolas)
Schedule::job(new SyncAssinaturasGatewayJob)->everyMinute()->withoutOverlapping();

// Polling das cobranças online de mensalidades dos alunos (subcontas das escolas)
Schedule::job(new SyncCobrancasContaGatewayJob)->everyMinute()->withoutOverlapping();
