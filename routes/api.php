<?php

/*
|--------------------------------------------------------------------------
| API Routes (legado mínimo)
|--------------------------------------------------------------------------
|
| Domínio vive nos ServiceProviders de app/Modules/{Nome}/routes.php
| (Core, Pessoas, Academico, Financeiro, Comercial, Espetaculos,
| Instrumentos, Notificacoes, Relatorios).
|
| Este arquivo permanece vazio de domínio de propósito (strangler).
|
*/

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // reservado — não adicionar rotas de domínio aqui
});
