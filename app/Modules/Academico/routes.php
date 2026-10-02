<?php

use App\Modules\Academico\Http\Controllers\AulaPresencaController;
use App\Modules\Academico\Http\Controllers\AulaTurmaController;
use App\Modules\Academico\Http\Controllers\CursoController;
use App\Modules\Academico\Http\Controllers\MaterialTurmaController;
use App\Modules\Academico\Http\Controllers\MatriculaController;
use App\Modules\Academico\Http\Controllers\NivelController;
use App\Modules\Academico\Http\Controllers\ProfessorMeController;
use App\Modules\Academico\Http\Controllers\ProfessorPainelController;
use App\Modules\Academico\Http\Controllers\ProfessorTurmaController;
use App\Modules\Academico\Http\Controllers\TurmaController;
use App\Modules\Academico\Http\Controllers\TurmaHorarioController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do módulo Acadêmico.
| Prefixadas com /api pelo AcademicoServiceProvider.
*/

Route::middleware(['auth:sanctum', 'ensure.instituicao.membership', 'ensure.senha.atualizada'])->group(function () {
    Route::middleware(['role:professor', 'ensure.perfil.professor'])->prefix('professor/me')->group(function () {
        Route::get('/', [ProfessorMeController::class, 'show']);
        Route::get('painel', [ProfessorPainelController::class, 'show']);
        Route::get('turmas', [ProfessorTurmaController::class, 'index']);
        Route::get('turmas/{turma}', [ProfessorTurmaController::class, 'show']);
    });

    Route::middleware('role:secretaria,admin')->group(function () {
        Route::apiResource('cursos', CursoController::class);
        Route::apiResource('niveis', NivelController::class);

        Route::patch('turmas/{turma}/status', [TurmaController::class, 'updateStatus']);
        Route::apiResource('turmas', TurmaController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('turma-horarios', TurmaHorarioController::class)
            ->parameters(['turma-horarios' => 'turmaHorario'])
            ->only(['store', 'update', 'destroy']);
        Route::apiResource('matriculas', MatriculaController::class)->only(['store', 'update', 'destroy']);
        Route::post('matriculas/{matricula}/gerar-mensalidades', [MatriculaController::class, 'gerarMensalidades']);
    });

    Route::middleware('role:secretaria,admin,professor')->group(function () {
        Route::apiResource('aulas-turmas', AulaTurmaController::class)
            ->parameters(['aulas-turmas' => 'aulaTurma']);
        Route::post('aulas-turmas/{aulaTurma}/presencas/sync', [AulaPresencaController::class, 'bulkStore']);
        Route::delete('aulas-turmas/{aulaTurma}/presencas', [AulaPresencaController::class, 'destroyAll']);
        Route::delete('presencas/{aulaPresenca}', [AulaPresencaController::class, 'destroy']);

        Route::apiResource('materiais-turmas', MaterialTurmaController::class)
            ->parameters(['materiais-turmas' => 'id'])
            ->only(['store', 'update', 'destroy']);
    });

    Route::apiResource('turmas', TurmaController::class)->only(['index', 'show']);
    Route::apiResource('turma-horarios', TurmaHorarioController::class)
        ->parameters(['turma-horarios' => 'turmaHorario'])
        ->only(['index', 'show']);
    Route::apiResource('matriculas', MatriculaController::class)->only(['index', 'show']);
    Route::get('/alunos/{aluno}/matriculas', [MatriculaController::class, 'getByAluno']);
    Route::get('turmas/{turma}/matriculas', [MatriculaController::class, 'getByTurma']);
    Route::get('matriculas/{matricula}/historico', [MatriculaController::class, 'getHistorico']);
    Route::get('matriculas/{matricula}/mensalidades', [MatriculaController::class, 'getMensalidades']);

    Route::get('aulas-turmas/{aulaTurma}/presencas', [AulaPresencaController::class, 'index']);
    Route::apiResource('materiais-turmas', MaterialTurmaController::class)
        ->parameters(['materiais-turmas' => 'id'])
        ->only(['index', 'show']);
    Route::get('turmas/{turma}/materiais', [MaterialTurmaController::class, 'getByTurma']);
});
