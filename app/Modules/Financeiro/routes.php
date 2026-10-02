<?php

use App\Modules\Financeiro\Http\Controllers\CarneController;
use App\Modules\Financeiro\Http\Controllers\CategoriaContaController;
use App\Modules\Financeiro\Http\Controllers\ConfiguracaoPixController;
use App\Modules\Financeiro\Http\Controllers\ContaController;
use App\Modules\Financeiro\Http\Controllers\ContaPagamentoController;
use App\Modules\Financeiro\Http\Controllers\ContratoController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo Financeiro.
| Prefixadas com /api pelo FinanceiroServiceProvider.
*/

Route::middleware(['auth:sanctum', 'ensure.instituicao.membership', 'ensure.plano.modulo:financeiro'])->group(function () {
    Route::middleware('role:secretaria,admin')->group(function () {
        Route::apiResource('contratos', ContratoController::class);
        Route::apiResource('categorias-contas', CategoriaContaController::class)
            ->parameters(['categorias-contas' => 'categoriaConta']);
        Route::apiResource('contas', ContaController::class)->only(['store', 'update', 'destroy']);
        Route::post('contas/pagamentos/lote', [ContaPagamentoController::class, 'storeEmLote']);
        Route::post('contas/{conta}/pagamentos', [ContaPagamentoController::class, 'store']);
        Route::delete('pagamentos/{pagamento}', [ContaPagamentoController::class, 'destroy']);

        Route::post('carne/gerar', [CarneController::class, 'gerar']);
        Route::get('configuracao-pix', [ConfiguracaoPixController::class, 'show']);
        Route::put('configuracao-pix', [ConfiguracaoPixController::class, 'update']);
    });

    Route::apiResource('contas', ContaController::class)->only(['index', 'show']);
    Route::get('contas/{conta}/pagamentos', [ContaPagamentoController::class, 'index']);
});
