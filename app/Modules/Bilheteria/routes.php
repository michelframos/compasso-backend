<?php

use App\Modules\Bilheteria\Http\Controllers\CupomIngressoController;
use App\Modules\Bilheteria\Http\Controllers\IngressoController;
use App\Modules\Bilheteria\Http\Controllers\TemplateIngressoController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'ensure.instituicao.membership',
    'ensure.plano.modulo:bilheteria',
    'role:secretaria,admin',
])->group(function () {
    Route::apiResource('templates-ingressos', TemplateIngressoController::class)
        ->parameters(['templates-ingressos' => 'templateIngresso']);
    Route::post('templates-ingressos/{templateIngresso}/fundo', [TemplateIngressoController::class, 'uploadFundo']);
    Route::delete('templates-ingressos/{templateIngresso}/fundo', [TemplateIngressoController::class, 'removerFundo']);
    Route::post('templates-ingressos/{templateIngresso}/elementos', [TemplateIngressoController::class, 'uploadElemento']);

    Route::apiResource('cupons-ingresso', CupomIngressoController::class)
        ->parameters(['cupons-ingresso' => 'cupomIngresso']);

    Route::get('ingressos', [IngressoController::class, 'index']);
    Route::get('ingressos/relatorio', [IngressoController::class, 'relatorio']);
    Route::get('ingressos/{ingresso}', [IngressoController::class, 'show']);
    Route::post('espetaculos/{espetaculo}/ingressos/gerar', [IngressoController::class, 'gerar']);
    Route::post('ingressos/{ingresso}/vender', [IngressoController::class, 'vender']);
    Route::post('ingressos/{ingresso}/cortesia', [IngressoController::class, 'cortesia']);
    Route::post('ingressos/{ingresso}/cancelar-venda', [IngressoController::class, 'cancelarVenda']);
    Route::post('ingressos/validar', [IngressoController::class, 'validar']);
    Route::post('ingressos/utilizar', [IngressoController::class, 'utilizar']);
});
