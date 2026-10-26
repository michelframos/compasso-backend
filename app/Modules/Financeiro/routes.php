<?php

use App\Modules\Financeiro\Http\Controllers\AlunoContratoController;
use App\Modules\Financeiro\Http\Controllers\AlunoFinanceiroController;
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

    Route::middleware(['ensure.senha.atualizada', 'role:aluno,responsavel', 'ensure.perfil.aluno'])
        ->prefix('aluno/me')
        ->group(function () {
            Route::get('contas/consolidado', [AlunoFinanceiroController::class, 'consolidado']);
            Route::get('contas', [AlunoFinanceiroController::class, 'contas']);
            Route::get('contas/{conta}/pix', [AlunoFinanceiroController::class, 'pix']);
            Route::post('carne', [AlunoFinanceiroController::class, 'carne']);
            Route::get('pagamentos/{pagamento}/recibo', [AlunoFinanceiroController::class, 'recibo']);
        });
});

Route::middleware(['auth:sanctum', 'ensure.instituicao.membership', 'ensure.senha.atualizada', 'role:aluno,responsavel', 'ensure.perfil.aluno'])
    ->prefix('aluno/me')
    ->group(function () {
        Route::get('contratos', [AlunoContratoController::class, 'index']);
        Route::get('contratos/{alunoContrato}/pdf', [AlunoContratoController::class, 'pdf']);
        Route::post('contratos/{alunoContrato}/aceite', [AlunoContratoController::class, 'aceite']);
    });
