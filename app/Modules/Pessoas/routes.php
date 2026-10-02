<?php

use App\Modules\Pessoas\Http\Controllers\AlunoController;
use App\Modules\Pessoas\Http\Controllers\MedidaAlunoController;
use App\Modules\Pessoas\Http\Controllers\ProfessorController;
use App\Modules\Pessoas\Http\Controllers\ResponsavelAlunoController;
use App\Modules\Pessoas\Http\Controllers\ResponsavelController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo Pessoas.
| Prefixadas com /api pelo PessoasServiceProvider.
*/

Route::middleware(['auth:sanctum', 'ensure.instituicao.membership'])->group(function () {
    Route::middleware('role:secretaria,admin')->group(function () {
        Route::get('alunos/list', [AlunoController::class, 'list']);
        Route::apiResource('alunos', AlunoController::class);
        Route::post('/alunos/{aluno}/contratos-avulsos', [AlunoController::class, 'storeContratoAvulso']);
        Route::delete('/alunos/{aluno}/contratos-avulsos/{alunoContrato}', [AlunoController::class, 'destroyContratoAvulso']);

        Route::apiResource('professores', ProfessorController::class)->parameters(['professores' => 'professor']);
        Route::apiResource('responsaveis', ResponsavelController::class)->parameters(['responsaveis' => 'responsavel']);

        Route::post('/alunos/{aluno}/responsaveis', [ResponsavelAlunoController::class, 'store']);
        Route::delete('/alunos/{aluno}/responsaveis/{responsavel}', [ResponsavelAlunoController::class, 'destroy']);

        Route::apiResource('medidas-alunos', MedidaAlunoController::class)->only(['store', 'update', 'destroy']);
    });

    Route::apiResource('medidas-alunos', MedidaAlunoController::class)->only(['index', 'show']);
    Route::get('/alunos/{id_aluno}/medidas', [MedidaAlunoController::class, 'getByAluno']);
    Route::get('/alunos/{aluno}/responsaveis', [ResponsavelAlunoController::class, 'index']);
    Route::get('/responsaveis/{responsavel}/alunos', [ResponsavelAlunoController::class, 'alunos']);
});
