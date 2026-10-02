<?php

use App\Modules\Relatorios\Http\Controllers\DashboardController;
use App\Modules\Relatorios\Http\Controllers\DiarioClasseProfessorController;
use App\Modules\Relatorios\Http\Controllers\RelatorioComercialController;
use App\Modules\Relatorios\Http\Controllers\RelatorioFinanceiroController;
use App\Modules\Relatorios\Http\Controllers\RelatorioInstrumentoController;
use App\Modules\Relatorios\Http\Controllers\RelatorioPedagogicoController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo Relatórios + Dashboard.
| Prefixadas com /api pelo RelatoriosServiceProvider.
| ResolveInstituicao é aplicado globalmente no grupo api (bootstrap).
*/

Route::middleware(['auth:sanctum', 'ensure.instituicao.membership'])->group(function () {
    Route::middleware(['ensure.senha.atualizada', 'role:professor', 'ensure.perfil.professor'])
        ->get('professor/me/turmas/{turma}/diario', [DiarioClasseProfessorController::class, 'show']);

    Route::middleware('role:secretaria,admin')->group(function () {
        Route::get('dashboard/resumo', [DashboardController::class, 'resumo']);
        Route::get('dashboard/financeiro', [DashboardController::class, 'financeiro']);
        Route::get('dashboard/operacional', [DashboardController::class, 'operacional']);

        Route::middleware('ensure.plano.modulo:relatorios')->prefix('relatorios')->group(function () {
            Route::prefix('financeiro')->group(function () {
                Route::get('inadimplencia', [RelatorioFinanceiroController::class, 'inadimplencia']);
                Route::get('fluxo-caixa', [RelatorioFinanceiroController::class, 'fluxoCaixa']);
                Route::get('fluxo-caixa/movimentos', [RelatorioFinanceiroController::class, 'fluxoCaixaMovimentos']);
            });

            Route::prefix('academico')->group(function () {
                Route::get('absenteismo', [RelatorioPedagogicoController::class, 'absenteismo']);
                Route::get('diario-classe', [RelatorioPedagogicoController::class, 'diarioClasse']);
            });

            Route::prefix('comercial')->group(function () {
                Route::get('funil-vendas', [RelatorioComercialController::class, 'funilVendas']);
                Route::get('aniversariantes', [RelatorioComercialController::class, 'aniversariantes']);
            });

            Route::prefix('logistica')->group(function () {
                Route::get('inventario', [RelatorioInstrumentoController::class, 'index']);
            });
        });
    });
});
