<?php

use App\Modules\Notificacoes\Http\Controllers\ConfiguracaoNotificacaoController;
use App\Modules\Notificacoes\Http\Controllers\ConfiguracaoWhatsappController;
use App\Modules\Notificacoes\Http\Controllers\ContaNotificacaoController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo Notificações.
| Prefixadas com /api pelo NotificacoesServiceProvider.
| ResolveInstituicao é aplicado globalmente no grupo api (bootstrap).
*/

Route::middleware(['auth:sanctum', 'ensure.instituicao.membership'])->group(function () {
    Route::middleware('role:secretaria,admin')->group(function () {
        Route::get('whatsapp/config', [ConfiguracaoWhatsappController::class, 'show']);
        Route::post('whatsapp/connect', [ConfiguracaoWhatsappController::class, 'connect']);
        Route::put('whatsapp/reconnect', [ConfiguracaoWhatsappController::class, 'reconnect']);
        Route::delete('whatsapp/disconnect', [ConfiguracaoWhatsappController::class, 'disconnect']);

        Route::get('notificacoes/config', [ConfiguracaoNotificacaoController::class, 'index']);
        Route::put('notificacoes/config', [ConfiguracaoNotificacaoController::class, 'update']);
        Route::post('notificacoes/disparar-agora', [ConfiguracaoNotificacaoController::class, 'dispararAgora']);

        Route::get('notificacoes/contas/{conta}/preview', [ContaNotificacaoController::class, 'preview']);
        Route::post('notificacoes/contas/{conta}/enviar', [ContaNotificacaoController::class, 'enviar']);
    });
});
