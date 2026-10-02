<?php

use App\Modules\Espetaculos\Http\Controllers\ApresentacaoAlunoController;
use App\Modules\Espetaculos\Http\Controllers\ApresentacaoController;
use App\Modules\Espetaculos\Http\Controllers\ApresentacaoProfessorController;
use App\Modules\Espetaculos\Http\Controllers\EnsaioController;
use App\Modules\Espetaculos\Http\Controllers\EspetaculoController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo Espetáculos.
| Prefixadas com /api pelo EspetaculosServiceProvider.
| ResolveInstituicao é aplicado globalmente no grupo api (bootstrap).
*/

Route::middleware(['auth:sanctum', 'ensure.instituicao.membership', 'ensure.plano.modulo:espetaculos'])->group(function () {
    Route::middleware('role:secretaria,admin')->group(function () {
        Route::apiResource('espetaculos', EspetaculoController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('apresentacoes', ApresentacaoController::class)
            ->parameters(['apresentacoes' => 'apresentacao'])
            ->only(['store', 'update', 'destroy']);
        Route::post('apresentacoes/{apresentacao}/gerar-cobrancas-figurino', [ApresentacaoAlunoController::class, 'gerarCobrancasEmLote']);
        Route::apiResource('apresentacoes-alunos', ApresentacaoAlunoController::class)
            ->parameters(['apresentacoes-alunos' => 'apresentacaoAluno'])
            ->only(['store', 'update', 'destroy']);
    });

    Route::apiResource('espetaculos', EspetaculoController::class)->only(['index', 'show']);
    Route::apiResource('apresentacoes', ApresentacaoController::class)
        ->parameters(['apresentacoes' => 'apresentacao'])
        ->only(['index', 'show']);
    Route::apiResource('apresentacoes-alunos', ApresentacaoAlunoController::class)
        ->parameters(['apresentacoes-alunos' => 'apresentacaoAluno'])
        ->only(['index', 'show']);

    Route::middleware(['ensure.senha.atualizada', 'role:professor,secretaria,admin'])->group(function () {
        Route::apiResource('ensaios', EnsaioController::class)
            ->parameters(['ensaios' => 'ensaio'])
            ->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware(['ensure.senha.atualizada', 'role:professor', 'ensure.perfil.professor'])
        ->prefix('professor/me')
        ->group(function () {
            Route::get('apresentacoes', [ApresentacaoProfessorController::class, 'index']);
            Route::get('apresentacoes/{apresentacao}', [ApresentacaoProfessorController::class, 'show']);
        });
});
